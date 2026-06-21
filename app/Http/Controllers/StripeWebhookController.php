<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FloatingBooking;
use App\Models\Payment;
use App\Models\User;
use App\Support\StripeConfig;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stripe webhooks for the one-time fallback rail. Stripe no longer carries
 * subscriptions — recurring memberships run through MobilePay — so this only
 * needs to confirm completed one-time Checkout sessions (course card fallback
 * and floating bookings).
 */
class StripeWebhookController extends Controller
{
    /** Reject signatures whose timestamp is older/newer than this many seconds (replay protection). */
    private const SIGNATURE_TOLERANCE = 300;

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = StripeConfig::webhookSecret();

        if ($secret && ! $this->verifySignature($payload, $signature, $secret)) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response()->json(['error' => 'bad payload'], 400);
        }

        // Dedupe: Stripe retries on non-2xx and may send the same event twice.
        // A duplicate insert (UNIQUE provider+event_id) means we already handled it.
        $eventId = $event['id'] ?? null;
        if ($eventId) {
            try {
                DB::table('webhook_events')->insert([
                    'provider' => 'stripe',
                    'event_id' => $eventId,
                    'type' => $event['type'] ?? '',
                    'created_at' => now(),
                ]);
            } catch (QueryException) {
                return response()->json(['ok' => true, 'duplicate' => true]);
            }
        }

        if (($event['type'] ?? '') === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event['data']['object'] ?? []);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Verify a Stripe webhook signature header (t=…,v1=…). Also rejects signatures
     * whose timestamp falls outside SIGNATURE_TOLERANCE to block replay attacks.
     */
    private function verifySignature(string $payload, ?string $header, string $secret): bool
    {
        if (! $header) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, null);
            $parts[$k][] = $v;
        }
        if (empty($parts['t'][0]) || empty($parts['v1'])) {
            return false;
        }
        $ts = (int) $parts['t'][0];
        if ($ts <= 0 || abs(time() - $ts) > self::SIGNATURE_TOLERANCE) {
            return false;
        }
        $signed = $parts['t'][0].'.'.$payload;
        $expected = hash_hmac('sha256', $signed, $secret);
        foreach ($parts['v1'] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function handleCheckoutCompleted(array $session): void
    {
        $userId = (int) ($session['metadata']['user_id'] ?? 0);
        $courseId = (int) ($session['metadata']['course_id'] ?? 0);
        $bookingId = (int) ($session['metadata']['booking_id'] ?? 0);
        $customerId = $session['customer'] ?? null;
        $paymentIntent = is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null;

        // Floating booking checkout — confirm even if the user never returned.
        if ($bookingId && ! $courseId) {
            $this->confirmFloatingBooking($bookingId, $session);

            return;
        }

        if (! $userId || ! $courseId) {
            return;
        }

        if ($customerId && ($user = User::find($userId)) && ! $user->stripe_id) {
            $user->forceFill(['stripe_id' => $customerId])->save();
        }

        $course = Course::find($courseId);
        if (! $course) {
            return;
        }

        // Card fallback for a course: one-time payment grants one month of access.
        $enrollment = Enrollment::firstOrNew(['user_id' => $userId, 'course_id' => $courseId]);
        $enrollment->status = 'active';
        $enrollment->provider = 'stripe';
        $enrollment->payment_method = 'card';
        $enrollment->stripe_subscription_id = null;
        $enrollment->enrolled_at = $enrollment->enrolled_at ?: now();
        $enrollment->canceled_at = null;
        $enrollment->cancel_at_period_end = false;
        $enrollment->current_period_end = now()->addMonth();
        $enrollment->save();

        Payment::updateOrCreate(
            ['idempotency_key' => 'stripe:onetime:'.($paymentIntent ?: $enrollment->id.':'.now()->timestamp)],
            [
                'user_id' => $userId,
                'enrollment_id' => $enrollment->id,
                'provider' => 'stripe',
                'kind' => Payment::KIND_ONE_TIME,
                'external_id' => $paymentIntent,
                'amount_cents' => (int) ($session['amount_total'] ?? $course->price_cents),
                'currency' => config('payments.currency', 'dkk'),
                'status' => Payment::CAPTURED,
            ],
        );
    }

    private function confirmFloatingBooking(int $bookingId, array $session): void
    {
        $booking = FloatingBooking::find($bookingId);
        if (! $booking) {
            return;
        }
        if ($booking->status === 'cancelled') {
            return;
        } // honor any cancel that landed first
        if ($booking->status === 'active' && $booking->paid_at) {
            return;
        } // already confirmed

        $paymentIntent = is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null;

        $booking->status = 'active';
        $booking->paid_at = $booking->paid_at ?: now();
        $booking->provider = $booking->provider ?: 'stripe';
        $booking->stripe_payment_intent_id = $paymentIntent ?: $booking->stripe_payment_intent_id;
        $booking->save();

        if ($paymentIntent) {
            Payment::updateOrCreate(
                ['idempotency_key' => 'stripe:onetime:'.$paymentIntent],
                [
                    'user_id' => $booking->user_id,
                    'floating_booking_id' => $booking->id,
                    'provider' => 'stripe',
                    'kind' => Payment::KIND_ONE_TIME,
                    'external_id' => $paymentIntent,
                    'amount_cents' => $booking->amount_cents,
                    'currency' => config('payments.currency', 'dkk'),
                    'status' => Payment::CAPTURED,
                ],
            );
        }
    }
}
