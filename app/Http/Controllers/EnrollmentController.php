<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Payments\Dto\MembershipRequest;
use App\Payments\Dto\OneTimeRequest;
use App\Payments\Gateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    /**
     * Primary checkout: a recurring MobilePay membership. Falls back to the
     * card (one-time) flow when MobilePay isn't available, and to free
     * enrollment for free/dev courses.
     */
    public function store(Request $request, Course $course, Gateway $gateway): RedirectResponse
    {
        if ($guard = $this->guard($request, $course)) {
            return $guard;
        }

        if (! $this->isPaid($course)) {
            return $this->enrollFree($request, $course);
        }

        $recurring = $gateway->recurring();
        if (! $recurring) {
            // MobilePay not configured — use the card fallback transparently.
            return $this->storeCard($request, $course, $gateway);
        }

        $user = $request->user();
        $enrollment = $this->startPending($user->id, $course->id, 'mobilepay');

        try {
            $result = $recurring->startMembership(new MembershipRequest(
                user: $user,
                amountCents: $course->price_cents,
                productName: $course->title,
                successUrl: route('enroll.return', $course),
                cancelUrl: route('courses.show', $course),
                metadata: ['user_id' => $user->id, 'course_id' => $course->id],
            ));
            $enrollment->update(['mobilepay_agreement_id' => $result->reference]);

            return redirect()->away($result->url);
        } catch (\Throwable $e) {
            return back()->withErrors(['enroll' => 'MobilePay-fejl: '.$e->getMessage()]);
        }
    }

    /** Card fallback: a one-time Stripe payment granting one month of access. */
    public function storeCard(Request $request, Course $course, Gateway $gateway): RedirectResponse
    {
        if ($guard = $this->guard($request, $course)) {
            return $guard;
        }

        if (! $this->isPaid($course)) {
            return $this->enrollFree($request, $course);
        }

        $provider = $gateway->fallback();
        if (! $provider->isConfigured()) {
            return back()->withErrors(['enroll' => 'Kortbetaling er ikke tilgængelig lige nu.']);
        }

        $user = $request->user();
        $enrollment = $this->startPending($user->id, $course->id, 'stripe');

        try {
            $result = $provider->startOneTime(new OneTimeRequest(
                user: $user,
                amountCents: $course->price_cents,
                description: $course->title.' — 1 måned',
                successUrl: route('enroll.return', $course).'?session_id={CHECKOUT_SESSION_ID}',
                cancelUrl: route('courses.show', $course),
                metadata: ['user_id' => $user->id, 'course_id' => $course->id],
            ));
            $enrollment->update(['stripe_subscription_id' => null]);

            return redirect()->away($result->url);
        } catch (\Throwable $e) {
            return back()->withErrors(['enroll' => 'Betalingsfejl: '.$e->getMessage()]);
        }
    }

    /**
     * Return URL after checkout. Reconciles immediately so the user sees the
     * result without waiting on the webhook (which is the source of truth).
     */
    public function returnFromCheckout(Request $request, Course $course, Gateway $gateway): RedirectResponse
    {
        $user = $request->user();
        $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();

        if ($enrollment && $enrollment->provider === 'mobilepay' && $enrollment->mobilepay_agreement_id) {
            try {
                if ($gateway->recurring()?->getMembershipStatus($enrollment->mobilepay_agreement_id) === 'active') {
                    $this->activate($enrollment);

                    return redirect()->route('courses.show', $course)->with('status', 'Du er tilmeldt. Vi ses!');
                }
            } catch (\Throwable) { /* webhook will catch up */
            }
        }

        if ($enrollment && $enrollment->provider === 'stripe' && ($sessionId = $request->query('session_id'))) {
            try {
                $result = $gateway->provider('stripe')->getPaymentStatus($sessionId);
                if ($result->isCaptured()) {
                    $this->activate($enrollment);
                    $this->recordCoursePayment($enrollment, $result->reference);

                    return redirect()->route('courses.show', $course)->with('status', 'Du er tilmeldt. Vi ses!');
                }
            } catch (\Throwable) { /* webhook will catch up */
            }
        }

        return redirect()->route('courses.show', $course)
            ->with('status', 'Betaling modtaget. Din tilmelding vises om et øjeblik.');
    }

    public function cancel(Request $request, Course $course, Gateway $gateway): RedirectResponse
    {
        // Typed confirmation — accepted case-insensitively but the rest of the
        // word must match exactly so accidental clicks can't cancel a membership.
        $confirm = trim((string) $request->input('confirm', ''));
        if (strcasecmp($confirm, 'Afmeld') !== 0) {
            return back()->withErrors(['cancel' => 'Skriv "Afmeld" for at bekræfte.']);
        }

        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['active', 'pending', 'past_due'])
            ->first();
        if (! $enrollment) {
            return back();
        }

        // MobilePay membership: stop the agreement. Access continues until
        // current_period_end; no further charges are created.
        if ($enrollment->isRecurringMembership()) {
            try {
                $gateway->recurring()?->stopMembership($enrollment->mobilepay_agreement_id);
            } catch (\Throwable $e) {
                return back()->withErrors(['cancel' => 'Kunne ikke afmelde hos MobilePay: '.$e->getMessage()]);
            }
            $enrollment->cancel_at_period_end = true;
            $enrollment->save();
            $msg = $enrollment->current_period_end
                ? 'Afmeldt. Du har adgang frem til '.$enrollment->current_period_end->format('d.m.Y').', og du bliver ikke opkrævet igen.'
                : 'Afmeldt. Du bliver ikke opkrævet igen.';

            return back()->with('status', $msg);
        }

        // Card one-time (or free): nothing to stop at the provider — access simply
        // lapses at current_period_end and isn't renewed.
        if ($enrollment->current_period_end && $enrollment->current_period_end->isFuture()) {
            $enrollment->update(['cancel_at_period_end' => true]);

            return back()->with('status', 'Afmeldt. Du har adgang frem til '.$enrollment->current_period_end->format('d.m.Y').'.');
        }

        $enrollment->update(['status' => 'canceled', 'canceled_at' => now()]);

        return back()->with('status', 'Tilmelding er annulleret.');
    }

    /* ---------------------------------------------------------- helpers -- */

    /** Shared pre-flight; returns a redirect to short-circuit, or null to proceed. */
    private function guard(Request $request, Course $course): ?RedirectResponse
    {
        abort_unless($course->is_active, 404);
        if ($request->user()->enrolledIn($course)) {
            return redirect()->route('courses.show', $course)->with('status', 'Du er allerede tilmeldt.');
        }
        if ($course->isFull()) {
            return back()->withErrors(['enroll' => 'Holdet er fuldt booket.']);
        }

        return null;
    }

    private function isPaid(Course $course): bool
    {
        return $course->price_cents > 0 && ! $course->free_enrollment;
    }

    /** Pre-create (or reset) a pending enrollment so we can reconcile if webhooks lag. */
    private function startPending(int $userId, int $courseId, string $provider): Enrollment
    {
        $enrollment = Enrollment::firstOrNew(['user_id' => $userId, 'course_id' => $courseId]);
        $enrollment->fill([
            'status' => 'pending',
            'provider' => $provider,
            'mobilepay_agreement_id' => null,
            'stripe_subscription_id' => null,
            'canceled_at' => null,
            'current_period_end' => null,
            'cancel_at_period_end' => false,
            'enrolled_at' => now(),
        ])->save();

        return $enrollment;
    }

    private function activate(Enrollment $enrollment): void
    {
        $enrollment->status = 'active';
        $enrollment->enrolled_at = $enrollment->enrolled_at ?: now();
        $enrollment->canceled_at = null;
        $enrollment->cancel_at_period_end = false;
        if (! $enrollment->current_period_end || $enrollment->current_period_end->isPast()) {
            $enrollment->current_period_end = now()->addMonth();
        }
        $enrollment->save();
    }

    private function recordCoursePayment(Enrollment $enrollment, string $chargeRef): void
    {
        Payment::updateOrCreate(
            ['idempotency_key' => 'stripe:onetime:'.$chargeRef],
            [
                'user_id' => $enrollment->user_id,
                'enrollment_id' => $enrollment->id,
                'provider' => 'stripe',
                'kind' => Payment::KIND_ONE_TIME,
                'external_id' => $chargeRef,
                'amount_cents' => $enrollment->course?->price_cents ?? 0,
                'currency' => config('payments.currency', 'dkk'),
                'status' => Payment::CAPTURED,
            ],
        );
    }

    private function enrollFree(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($user, $course) {
            $course->refresh();
            if ($course->isFull()) {
                return;
            }
            Enrollment::updateOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                ['status' => 'active', 'provider' => null, 'enrolled_at' => now(), 'canceled_at' => null, 'cancel_at_period_end' => false],
            );
        });

        return redirect()->route('courses.show', $course)->with('status', 'Du er tilmeldt. Vi ses!');
    }
}
