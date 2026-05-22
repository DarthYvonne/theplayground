<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $enrolledCourses = Course::with('trainer')
            ->whereIn('id', $user->activeEnrollments()->pluck('course_id'))
            ->get();
        $trainerCourses = $user->isTrainer()
            ? Course::with('trainer')->where('trainer_id', $user->id)->get()
            : collect();
        return view('dashboard', compact('enrolledCourses','trainerCourses'));
    }
}
