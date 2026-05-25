<?php

namespace App\Http\Controllers;

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
            return redirect()->route('courses.show', $course)->with('status', 'Du er allerede tilmeldt.');
        }
        if ($course->isFull()) {
            return back()->withErrors(['enroll' => 'Holdet er fuldt booket.']);
        }

        // Paid flow when Stripe is configured. `free_enrollment` lets admins
        // bypass Stripe entirely (testing/dev) regardless of the price.
        if (StripeConfig::isConfigured() && $course->price_cents > 0 && !$course->free_enrollment) {
            if (!$course->stripe_price_id) {
                return back()->withErrors(['enroll' => 'Holdet mangler en Stripe-pris. Bed admin om at gemme det igen.']);
            }
            try {
                $session = StripeService::createCheckoutSession(
                    $user,
                    $course,
                    route('enroll.return', $course) . '?session_id={CHECKOUT_SESSION_ID}',
                    route('courses.show', $course),
                );
                // Pre-create (or reset) a pending enrollment so we can reconcile if
                // webhooks lag. If a stale row exists (canceled by the expiry sweep,
                // or canceled previously), reset it so the user starts fresh.
                $enrollment = Enrollment::firstOrNew(['user_id' => $user->id, 'course_id' => $course->id]);
                $enrollment->status = 'pending';
                $enrollment->stripe_subscription_id = null;
                $enrollment->canceled_at = null;
                $enrollment->current_period_end = null;
                $enrollment->cancel_at_period_end = false;
                $enrollment->enrolled_at = now();
                $enrollment->save();
                return redirect()->away($session['url']);
            } catch (\Throwable $e) {
                return back()->withErrors(['enroll' => 'Stripe-fejl: ' . $e->getMessage()]);
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

        return redirect()->route('courses.show', $course)->with('status', 'Du er tilmeldt. Vi ses!');
    }

    public function returnFromCheckout(Request $request, Course $course): RedirectResponse
    {
        $sessionId = $request->query('session_id');
        if ($sessionId && StripeConfig::isConfigured()) {
            try {
                $s = StripeService::retrieveCheckoutSession($sessionId);
                $sCourseId = (int) ($s['metadata']['course_id'] ?? 0);
                if ($sCourseId === $course->id && ($s['status'] ?? '') === 'complete') {
                    $enrollment = Enrollment::firstOrNew(['user_id' => $request->user()->id, 'course_id' => $course->id]);
                    $enrollment->status = 'active';
                    $enrollment->stripe_subscription_id = $s['subscription'] ?? $enrollment->stripe_subscription_id;
                    $enrollment->enrolled_at = $enrollment->enrolled_at ?: now();
                    $enrollment->canceled_at = null;
                    $enrollment->cancel_at_period_end = false;
                    // Pull period end so the UI can show "Adgang frem til X" without waiting on the webhook.
                    if ($enrollment->stripe_subscription_id) {
                        try {
                            $sub = StripeService::retrieveSubscription($enrollment->stripe_subscription_id);
                            if (isset($sub['current_period_end'])) {
                                $enrollment->current_period_end = \Carbon\Carbon::createFromTimestamp((int) $sub['current_period_end']);
                            }
                        } catch (\Throwable) { /* webhook will catch up */ }
                    }
                    $enrollment->save();
                    return redirect()->route('courses.show', $course)->with('status', 'Du er tilmeldt. Vi ses!');
                }
            } catch (\Throwable) {
                // Fall through to the webhook-will-catch-up message.
            }
        }
        return redirect()->route('courses.show', $course)
            ->with('status', 'Betaling modtaget. Din tilmelding vises om et øjeblik.');
    }

    public function cancel(Request $request, Course $course): RedirectResponse
    {
        // Typed confirmation — accepted case-insensitively but the rest of the
        // word must match exactly so accidental clicks can't cancel a sub.
        $confirm = trim((string) $request->input('confirm', ''));
        if (strcasecmp($confirm, 'Afmeld') !== 0) {
            return back()->withErrors(['cancel' => 'Skriv "Afmeld" for at bekræfte.']);
        }

        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['active','pending','past_due'])
            ->first();
        if (!$enrollment) return back();

        // Paid subscription: tell Stripe to stop renewing. The user keeps access
        // until current_period_end; Stripe sends customer.subscription.deleted
        // when the period actually ends and our webhook flips status to canceled.
        if ($enrollment->stripe_subscription_id && StripeConfig::isConfigured()) {
            try {
                $sub = StripeService::cancelSubscriptionAtPeriodEnd($enrollment->stripe_subscription_id);
                $periodEnd = isset($sub['current_period_end']) ? \Carbon\Carbon::createFromTimestamp($sub['current_period_end']) : null;
                $enrollment->cancel_at_period_end = true;
                if ($periodEnd) $enrollment->current_period_end = $periodEnd;
                $enrollment->save();
                $msg = $periodEnd
                    ? 'Afmeldt. Du har adgang frem til ' . $periodEnd->format('d.m.Y') . ', og du bliver ikke opkrævet igen.'
                    : 'Afmeldt. Du bliver ikke opkrævet igen.';
                return back()->with('status', $msg);
            } catch (\Throwable $e) {
                return back()->withErrors(['cancel' => 'Kunne ikke afmelde i Stripe: ' . $e->getMessage()]);
            }
        }

        // No Stripe subscription (free enrollment or pending without paid sub): cancel immediately.
        $enrollment->update(['status' => 'canceled', 'canceled_at' => now()]);
        return back()->with('status', 'Tilmelding er annulleret.');
    }
}
