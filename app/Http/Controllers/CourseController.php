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

        $byDay = [];
        foreach (array_keys(Course::WEEKDAYS) as $day) $byDay[$day] = [];
        foreach ($courses as $c) {
            foreach ($c->weekdaysList() as $day) {
                if (isset($byDay[$day])) $byDay[$day][] = $c;
            }
        }
        $unscheduled = $courses->filter(fn ($c) => empty($c->weekdaysList()))->values();

        return view('courses.calendar', compact('byDay', 'unscheduled'));
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
