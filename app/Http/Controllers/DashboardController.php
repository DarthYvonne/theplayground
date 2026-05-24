<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function feed(Request $request)
    {
        return view('dashboard.feed', ['user' => $request->user()]);
    }

    public function hold(Request $request)
    {
        $user = $request->user();
        $enrolledCourses = Course::with('trainer')
            ->whereIn('id', $user->activeEnrollments()->pluck('course_id'))
            ->get();
        $trainerCourses = $user->isTrainer()
            ? Course::with('trainer')->where('trainer_id', $user->id)->get()
            : collect();
        return view('dashboard.hold', compact('enrolledCourses', 'trainerCourses'));
    }
}
