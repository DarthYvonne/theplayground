<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCancellation;
use App\Support\CalendarWeek;
use App\Support\ScheduleGrid;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function index(Request $request) {
        $user = $request->user();
        $courses = $user->trainerCourses()
            ->with('trainers')
            ->orderByDesc('is_active')
            ->orderBy('title')
            ->get();
        return view('trainer.index', compact('courses'));
    }

    public function calendar(Request $request) {
        $user = $request->user();
        $ctx = CalendarWeek::resolveContext($request);

        // Everything this trainer is connected to, not only what they teach: a
        // trainer is also a member, and their own hold, fællestræning and 1:1
        // belong on their week too.
        $joined = array_flip($user->enrollments()->where('status', 'active')->withinPeriod()->pluck('course_id')->all());
        $paid = $user->hasPaidMembership();

        $courses = Course::with(['trainers', 'schedules'])
            ->where('is_active', true)
            ->visibleTo($user)
            ->orderBy('title')
            ->get()
            ->filter(fn (Course $c) => $c->trainers->contains('id', $user->id)
                || isset($joined[$c->id])
                || ($c->isFaellestraening() && $paid)
                || ($c->isPersonlig() && $c->member_id === $user->id))
            ->values();

        // Green marks what they are responsible for, separating a session they
        // run from one they merely turn up to.
        $connectedIds = $courses->filter(fn (Course $c) => $c->trainers->contains('id', $user->id))->pluck('id')->all();

        $byDay = ScheduleGrid::byDay($courses, array_keys(Course::WEEKDAYS));
        $unscheduled = $courses->filter(fn ($c) => empty($c->weekdaysList()))->values();
        $weekendCourses = collect($byDay['sat'] ?? [])->concat($byDay['sun'] ?? [])
            ->map(fn ($slot) => $slot->course)->unique('id')->values();

        $cancelledMap = CourseCancellation::mapForRange($courses->pluck('id')->all(), $ctx['rangeStart'], $ctx['rangeEnd']);
        $monday = $ctx['monday'];
        $monthAnchor = $ctx['monthAnchor'];
        $view = $ctx['view'];

        return view('trainer.calendar', compact(
            'byDay', 'unscheduled', 'weekendCourses', 'monday', 'monthAnchor',
            'view', 'cancelledMap', 'connectedIds'
        ));
    }

    public function storeCancellation(Request $request, Course $course): RedirectResponse {
        $this->authorize($request, $course);
        $data = $request->validate([
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $date = Carbon::parse($data['occurrence_date'])->startOfDay();
        $key = CalendarWeek::dateKey($date);
        if (!in_array($key, $course->weekdaysList(), true)) {
            return back()->withErrors(['occurrence_date' => 'Holdet er ikke planlagt på den dag.']);
        }
        CourseCancellation::updateOrCreate(
            ['course_id' => $course->id, 'occurrence_date' => $date->toDateString()],
            ['reason' => $data['reason'] ?? null, 'cancelled_by' => $request->user()->id],
        );
        return back()->with('status', 'Aflyst: ' . $course->title . ' (' . $date->format('d/m/Y') . ').');
    }

    public function destroyCancellation(Request $request, Course $course): RedirectResponse {
        $this->authorize($request, $course);
        $data = $request->validate([
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
        ]);
        CourseCancellation::where('course_id', $course->id)
            ->whereDate('occurrence_date', $data['occurrence_date'])
            ->delete();
        return back()->with('status', 'Genåbnet: ' . $course->title . ' (' . Carbon::parse($data['occurrence_date'])->format('d/m/Y') . ').');
    }

    public function participants(Request $request, Course $course) {
        $this->authorize($request, $course);
        $enrollments = $course->enrollments()->with('user')->where('status','active')->orderBy('enrolled_at')->get();
        return view('trainer.participants', compact('course','enrollments'));
    }

    private function authorize(Request $request, Course $course): void {
        $u = $request->user();
        abort_unless($u->isOwner() || $course->hasTrainer($u), 403);
    }
}
