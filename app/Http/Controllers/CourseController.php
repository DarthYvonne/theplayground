<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCancellation;
use App\Models\Enrollment;
use App\Models\User;
use App\Payments\Gateway;
use App\Support\CalendarWeek;
use App\Support\ScheduleGrid;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function faellestraening(Request $request)
    {
        $courses = Course::with(['trainers', 'schedules'])
            ->faellestraening()
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        return view('courses.faellestraening', compact('courses'));
    }

    public function personlig(Request $request)
    {
        $user = $request->user();

        // A personlig træning is a private arrangement between two people —
        // there is nothing here for a guest to browse.
        if (! $user) {
            return view('courses.personlig', ['courses' => collect()]);
        }

        $courses = Course::with(['trainers', 'schedules', 'member'])
            ->personlig()
            // An owner sees drafts too, matching show(); everyone else sees the
            // ones they are in — a trainer theirs, a member theirs.
            ->when(! $user->isOwner(), fn ($q) => $q
                ->where('is_active', true)
                ->where(fn ($q) => $q
                    ->where('member_id', $user->id)
                    ->orWhereHas('trainers', fn ($t) => $t->whereKey($user->id))))
            ->orderBy('title')
            ->get();

        return view('courses.personlig', compact('courses'));
    }

    public function index(Request $request)
    {
        $courses = Course::with(['trainers', 'schedules'])
            ->hold()
            ->where('is_active', true)
            ->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('created_at')
            ->get();

        // past_due counts as joined: a failed payment should not drop the member's
        // own hold back into the pile. pending does not — they never finished paying.
        $enrolledIds = $request->user()
            ? $request->user()->enrollments()->whereIn('status', ['active', 'past_due'])->pluck('course_id')->flip()
            : collect();

        // The member's own hold float to the top; PHP's sort is stable, so the
        // rest keep their newest-first order.
        $courses = $courses->sortByDesc(fn (Course $c) => $enrolledIds->has($c->id))->values();

        return view('courses.index', compact('courses', 'enrolledIds'));
    }

    public function calendar(Request $request)
    {
        $ctx = CalendarWeek::resolveContext($request);

        $courses = Course::with(['trainers', 'schedules'])
            ->where('is_active', true)
            // Somebody else's personlig træning is none of your business, and it
            // would otherwise appear on the shared weekly calendar by name.
            ->visibleTo($request->user())
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

    public function show(Course $course, Request $request, Gateway $gateway)
    {
        // A trainer sees their own drafts: they can create a training but not
        // edit it, so 404ing them out of the one they just made is a dead end.
        $viewer = $request->user();
        if (! $course->is_active && ! ($viewer?->isOwner() || $course->hasTrainer($viewer))) {
            abort(404);
        }
        // 404 rather than 403: a stranger must not be able to confirm that a
        // given personlig træning exists at all.
        abort_unless($course->isVisibleTo($request->user()), 404);
        $course->load(['trainers', 'member']);
        $user = $request->user();
        $isEnrolled = $user?->enrolledIn($course) ?? false;
        $enrollment = $user
            ? Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->whereIn('status', ['active', 'past_due', 'pending'])->first()
            : null;

        return view('courses.show', [
            'course' => $course,
            'isEnrolled' => $isEnrolled,
            'enrollment' => $enrollment,
            // Fællestræning is included in an existing membership, so the page
            // shows whether this viewer is covered instead of a checkout button.
            'isCovered' => $course->isFaellestraening() && (bool) $user?->hasPaidMembership(),
            // Only the named member of a personlig træning gets a checkout.
            'canEnroll' => $course->isEnrollableBy($user),
            'mobilePayAvailable' => $gateway->recurringAvailable(),
            'cardAvailable' => $gateway->fallback()->isConfigured(),
            'title' => $course->title,
        ]);
    }

    public function members(Course $course, Request $request)
    {
        $u = $request->user();
        // A fællestræning has no roster to show — every paying member may turn up.
        abort_unless($course->hasMemberList(), 404);
        abort_unless($course->grantsAccessTo($u), 403);

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
