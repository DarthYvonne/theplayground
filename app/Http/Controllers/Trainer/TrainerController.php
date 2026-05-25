<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCancellation;
use App\Support\CalendarWeek;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function index(Request $request) {
        $user = $request->user();
        $courses = Course::with('trainer')
            ->where('trainer_id', $user->id)
            ->orderByDesc('is_active')
            ->orderBy('title')
            ->get();
        return view('trainer.index', compact('courses'));
    }

    public function calendar(Request $request) {
        $user = $request->user();
        $ctx = CalendarWeek::resolveContext($request);

        $courses = Course::with('trainer')
            ->where('trainer_id', $user->id)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->orderBy('title')
            ->get();

        $byDay = [];
        foreach (array_keys(Course::WEEKDAYS) as $day) $byDay[$day] = [];
        foreach ($courses as $c) {
            foreach ($c->weekdaysList() as $day) {
                if (isset($byDay[$day])) $byDay[$day][] = $c;
            }
        }
        $unscheduled = $courses->filter(fn ($c) => empty($c->weekdaysList()))->values();
        $weekendCourses = collect($byDay['sat'] ?? [])->concat($byDay['sun'] ?? [])->unique('id')->values();

        $cancelledMap = CourseCancellation::mapForRange($courses->pluck('id')->all(), $ctx['rangeStart'], $ctx['rangeEnd']);
        $monday = $ctx['monday'];
        $monthAnchor = $ctx['monthAnchor'];
        $view = $ctx['view'];

        return view('trainer.calendar', compact(
            'byDay', 'unscheduled', 'weekendCourses', 'monday', 'monthAnchor',
            'view', 'cancelledMap'
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
        abort_unless($u->isOwner() || $course->trainer_id === $u->id, 403);
    }
}
