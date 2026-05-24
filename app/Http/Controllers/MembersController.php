<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;

class MembersController extends Controller
{
    public function index(Request $request)
    {
        $users = User::orderBy('name')
            ->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status', 'active')])
            ->withCount('trainerCourses')
            ->get();

        return view('members.index', compact('users'));
    }

    public function show(Request $request, User $user)
    {
        $enrolledCourses = Course::with('trainer')
            ->whereIn('id', $user->activeEnrollments()->pluck('course_id'))
            ->orderBy('title')
            ->get();

        $trainerCourses = $user->trainerCourses()
            ->with('trainer')
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        return view('members.show', [
            'member' => $user,
            'enrolledCourses' => $enrolledCourses,
            'trainerCourses' => $trainerCourses,
            'isSelf' => $request->user()->id === $user->id,
        ]);
    }
}
