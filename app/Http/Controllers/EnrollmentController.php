<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    /**
     * Begin enrollment. If Stripe is configured, redirect to Stripe Checkout.
     * If not (dev mode), create the enrollment immediately so the rest of the
     * app is usable end-to-end.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();
        abort_unless($course->is_active, 404);

        if ($user->enrolledIn($course)) {
            return redirect()->route('courses.show', $course)->with('status', 'Already enrolled.');
        }

        $created = DB::transaction(function () use ($user, $course) {
            $course->refresh();
            if ($course->isFull()) return null;
            return Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
        });

        if (!$created) {
            return back()->withErrors(['enroll' => 'Course is full.']);
        }

        AppNotification::create([
            'user_id' => $course->trainer_id,
            'type' => 'enrollment',
            'title' => $user->name . ' enrolled in ' . $course->title,
            'link' => route('courses.show', $course),
            'course_id' => $course->id,
            'actor_id' => $user->id,
        ]);

        return redirect()->route('courses.show', $course)->with('status', 'You\'re in. See you in class.');
    }

    public function cancel(Request $request, Course $course): RedirectResponse
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)->where('course_id', $course->id)->where('status','active')->first();
        if (!$enrollment) return back();
        $enrollment->update(['status' => 'canceled', 'canceled_at' => now()]);
        return back()->with('status', 'Enrollment canceled.');
    }
}
