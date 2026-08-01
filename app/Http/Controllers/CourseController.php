<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCancellation;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use App\Payments\Gateway;
use App\Support\CalendarWeek;
use App\Support\ScheduleGrid;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::with('trainers')
            ->where('is_active', true)
            ->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('created_at')
            ->get();

        return view('courses.index', compact('courses'));
    }

    public function calendar(Request $request)
    {
        $ctx = CalendarWeek::resolveContext($request);

        $courses = Course::with(['trainers', 'schedules'])
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        $weekdayKeys = ['mon', 'tue', 'wed', 'thu', 'fri'];
        $byDay = ScheduleGrid::byDay($courses, $weekdayKeys);

        $weekendCourses = $courses->filter(function (Course $c) use ($weekdayKeys) {
            $days = $c->weekdaysList();
            return array_intersect($days, ['sat', 'sun']) && ! array_intersect($days, $weekdayKeys);
        })->values();
        $unscheduled = $courses->filter(fn (Course $c) => empty($c->weekdaysList()))->values();

        $enrolledIds = $request->user()?->activeEnrollments()->pluck('course_id')->all() ?? [];
        $cancelledMap = CourseCancellation::mapForRange($courses->pluck('id')->all(), $ctx['rangeStart'], $ctx['rangeEnd']);

        $monday = $ctx['monday'];
        $monthAnchor = $ctx['monthAnchor'];
        $view = $ctx['view'];

        return view('courses.calendar', compact(
            'byDay', 'unscheduled', 'weekendCourses', 'enrolledIds',
            'monday', 'monthAnchor', 'view', 'cancelledMap'
        ));
    }

    public function mine(Request $request)
    {
        $user = $request->user();

        // past_due/pending are included on purpose: a failed payment must not make
        // the hold silently disappear from the one page the member checks.
        $enrollments = Course::query()
            ->with(['trainers', 'schedules'])
            ->whereIn('id', $user->currentEnrollments()->pluck('course_id'))
            ->get()
            ->keyBy('id');

        $statuses = $user->currentEnrollments()->pluck('status', 'course_id');
        $courseIds = $enrollments->keys()->all();

        $now = Carbon::now();
        $cancelled = [];
        foreach (CourseCancellation::mapForRange($courseIds, $now->copy()->startOfDay(), $now->copy()->addDays(14)) as $c) {
            $cancelled[$c->course_id][] = $c->occurrence_date->toDateString();
        }

        $unread = $this->unreadByCourse($user, $courseIds);
        $today = $now->copy()->startOfDay();

        $tiles = $enrollments->map(function (Course $course) use ($now, $today, $cancelled, $unread, $statuses) {
            $skip = $cancelled[$course->id] ?? [];
            $next = $course->nextOccurrence($now, $skip);

            return [
                'course' => $course,
                'next' => $next,
                'next_label' => $next?->label($now),
                'is_today' => $next && $next->start->isSameDay($today),
                'cancelled_today' => $course->runsOn($today) && in_array($today->toDateString(), $skip, true),
                'unread' => $unread[$course->id] ?? 0,
                'status' => $statuses[$course->id] ?? 'active',
            ];
        })
            ->sortBy(fn ($t) => $t['next']?->start->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        return view('courses.mine', [
            'tiles' => $tiles,
            'needsPayment' => $tiles->filter(fn ($t) => $t['status'] !== 'active')->values(),
        ]);
    }

    /**
     * Unread course messages per course, using the same cutoff rule as
     * User::unreadMessageCount(). One query per course, as elsewhere — a member
     * is on a handful of holds.
     *
     * @param  array<int> $courseIds
     * @return array<int, int>
     */
    private function unreadByCourse(User $user, array $courseIds): array
    {
        if (empty($courseIds)) return [];

        $reads = MessageRead::where('user_id', $user->id)->whereIn('course_id', $courseIds)->pluck('last_read_at', 'course_id');

        $counts = [];
        foreach ($courseIds as $cid) {
            $q = Message::where('channel_type', 'course')->where('course_id', $cid)->where('user_id', '!=', $user->id);
            if ($cutoff = $reads[$cid] ?? null) $q->where('created_at', '>', $cutoff);
            $counts[$cid] = $q->count();
        }

        return $counts;
    }

    public function show(Course $course, Request $request, Gateway $gateway)
    {
        if (! $course->is_active && ! ($request->user()?->isOwner())) {
            abort(404);
        }
        $course->load('trainers');
        $user = $request->user();
        $isEnrolled = $user?->enrolledIn($course) ?? false;
        $enrollment = $user
            ? Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->whereIn('status', ['active', 'past_due', 'pending'])->first()
            : null;

        return view('courses.show', [
            'course' => $course,
            'isEnrolled' => $isEnrolled,
            'enrollment' => $enrollment,
            'mobilePayAvailable' => $gateway->recurringAvailable(),
            'cardAvailable' => $gateway->fallback()->isConfigured(),
            'title' => $course->title,
        ]);
    }

    public function members(Course $course, Request $request)
    {
        $u = $request->user();
        abort_unless($u->isOwner() || $course->hasTrainer($u) || $u->enrolledIn($course), 403);

        $course->load('trainers');
        $memberIds = Enrollment::where('course_id', $course->id)
            ->where('status', 'active')
            ->pluck('user_id');
        $members = User::whereIn('id', $memberIds)->orderBy('name')->get();

        return view('courses.members', [
            'course' => $course,
            'members' => $members,
            'title' => $course->title,
        ]);
    }
}
