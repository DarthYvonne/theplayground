<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::with('trainer')
            ->where('is_active', true)
            ->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status','active')])
            ->orderByDesc('created_at')
            ->get();
        return view('courses.index', compact('courses'));
    }

    public function calendar(Request $request)
    {
        $courses = Course::with('trainer')
            ->where('is_active', true)
            ->orderBy('start_time')
            ->orderBy('title')
            ->get();

        $weekdayKeys = ['mon','tue','wed','thu','fri'];
        $byDay = array_fill_keys($weekdayKeys, []);
        $weekendCourses = collect();
        foreach ($courses as $c) {
            $days = $c->weekdaysList();
            foreach ($days as $day) {
                if (isset($byDay[$day])) $byDay[$day][] = $c;
            }
            if (array_intersect($days, ['sat','sun']) && !array_intersect($days, $weekdayKeys)) {
                $weekendCourses->push($c);
            }
        }
        $unscheduled = $courses->filter(fn ($c) => empty($c->weekdaysList()))->values();

        $enrolledIds = $request->user()?->activeEnrollments()->pluck('course_id')->all() ?? [];

        return view('courses.calendar', compact('byDay', 'unscheduled', 'weekendCourses', 'enrolledIds'));
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        $enrolledCourses = Course::with('trainer')
            ->whereIn('id', $user->activeEnrollments()->pluck('course_id'))
            ->get();
        return view('courses.mine', compact('enrolledCourses'));
    }

    public function show(Course $course, Request $request)
    {
        if (!$course->is_active && !($request->user()?->isOwner())) abort(404);
        $course->load('trainer');
        $isEnrolled = $request->user()?->enrolledIn($course) ?? false;
        return view('courses.show', [
            'course' => $course,
            'isEnrolled' => $isEnrolled,
            'title' => $course->title,
        ]);
    }
}
