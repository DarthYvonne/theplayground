<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Support\StripeConfig;
use App\Support\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();
        abort_unless($course->is_active, 404);

        if ($user->enrolledIn($course)) {
            return redirect()->route('courses.show', $course)->with('status', 'Already enrolled.');
        }
        if ($course->isFull()) {
            return back()->withErrors(['enroll' => 'Course is full.']);
        }

        // Paid flow when Stripe is configured.
        if (StripeConfig::isConfigured() && $course->price_cents > 0) {
            if (!$course->stripe_price_id) {
                return back()->withErrors(['enroll' => 'This course is missing its Stripe price. Ask the admin to re-save it.']);
            }
            try {
                $session = StripeService::createCheckoutSession(
                    $user,
                    $course,
                    route('enroll.return', $course) . '?session_id={CHECKOUT_SESSION_ID}',
                    route('courses.show', $course),
                );
                // Pre-create a pending enrollment so we can reconcile if webhooks lag.
                Enrollment::firstOrCreate(
                    ['user_id' => $user->id, 'course_id' => $course->id],
                    ['status' => 'pending', 'enrolled_at' => now()]
                );
                return redirect()->away($session['url']);
            } catch (\Throwable $e) {
                return back()->withErrors(['enroll' => 'Stripe error: ' . $e->getMessage()]);
            }
        }

        // Free / dev fallback: create enrollment directly.
        DB::transaction(function () use ($user, $course) {
            $course->refresh();
            if ($course->isFull()) return;
            Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
        });

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

    public function returnFromCheckout(Request $request, Course $course): RedirectResponse
    {
        // Webhook is the source of truth; this just gives the user instant feedback.
        return redirect()->route('courses.show', $course)
            ->with('status', 'Payment received. Your enrollment will appear in a moment.');
    }

    public function cancel(Request $request, Course $course): RedirectResponse
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['active','pending'])
            ->first();
        if (!$enrollment) return back();

        // If we have a Stripe subscription, send the user to the portal to cancel there.
        if ($enrollment->stripe_subscription_id && StripeConfig::isConfigured()) {
            $url = StripeService::customerPortalUrl($request->user(), route('dashboard'));
            if ($url) return redirect()->away($url);
        }

        $enrollment->update(['status' => 'canceled', 'canceled_at' => now()]);
        return back()->with('status', 'Enrollment canceled.');
    }
}
