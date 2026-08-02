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
            ->with([
                'activeEnrollments:id,user_id,course_id',
                'trainerCourses:id',
            ])
            ->get();

        $courses = Course::where('is_active', true)->orderBy('title')->get(['id', 'title']);

        return view('members.index', compact('users', 'courses'));
    }

    public function show(Request $request, User $user)
    {
        // A profile must not advertise this member's personlig træning to whoever
        // happens to look them up.
        $enrolledCourses = Course::with('trainers')
            ->whereIn('id', $user->activeEnrollments()->pluck('course_id'))
            ->visibleTo($request->user())
            ->orderBy('title')
            ->get();

        $trainerCourses = $user->trainerCourses()
            ->with('trainers')
            ->where('is_active', true)
            ->visibleTo($request->user())
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
