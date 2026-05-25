<?php

namespace App\Http\Controllers;

use App\Mail\PaymentFailedMail;
use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\StripeConfig;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class StripeWebhookController extends Controller
{
    /** Reject signatures whose timestamp is older/newer than this many seconds (replay protection). */
    private const SIGNATURE_TOLERANCE = 300;

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = StripeConfig::webhookSecret();

        if ($secret && !$this->verifySignature($payload, $signature, $secret)) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) return response()->json(['error' => 'bad payload'], 400);

        // Dedupe: Stripe retries on non-2xx, and may also send the same event twice.
        // We rely on a UNIQUE constraint on event_id; a duplicate insert means we
        // already processed this event and can ack without doing anything.
        $eventId = $event['id'] ?? null;
        if ($eventId) {
            try {
                DB::table('stripe_events')->insert([
                    'event_id' => $eventId,
                    'type' => $event['type'] ?? '',
                    'created_at' => now(),
                ]);
            } catch (QueryException) {
                return response()->json(['ok' => true, 'duplicate' => true]);
            }
        }

        match ($event['type'] ?? '') {
            'checkout.session.completed'      => $this->handleCheckoutCompleted($event['data']['object'] ?? []),
            'customer.subscription.created',
            'customer.subscription.updated'   => $this->syncSubscription($event['data']['object'] ?? []),
            'customer.subscription.deleted'   => $this->cancelSubscription($event['data']['object'] ?? []),
            'invoice.payment_failed'          => $this->handleInvoiceFailed($event['data']['object'] ?? []),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    /**
     * Verify a Stripe webhook signature header (t=…,v1=…). Also rejects signatures
     * whose timestamp falls outside SIGNATURE_TOLERANCE to block replay attacks.
     */
    private function verifySignature(string $payload, ?string $header, string $secret): bool
    {
        if (!$header) return false;
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, null);
            $parts[$k][] = $v;
        }
        if (empty($parts['t'][0]) || empty($parts['v1'])) return false;
        $ts = (int) $parts['t'][0];
        if ($ts <= 0 || abs(time() - $ts) > self::SIGNATURE_TOLERANCE) return false;
        $signed = $parts['t'][0] . '.' . $payload;
        $expected = hash_hmac('sha256', $signed, $secret);
        foreach ($parts['v1'] as $candidate) {
            if (hash_equals($expected, $candidate)) return true;
        }
        return false;
    }

    private function handleCheckoutCompleted(array $session): void
    {
        $userId = (int) ($session['metadata']['user_id'] ?? 0);
        $courseId = (int) ($session['metadata']['course_id'] ?? 0);
        $subId = $session['subscription'] ?? null;
        $customerId = $session['customer'] ?? null;
        if (!$userId || !$courseId) return;

        if ($customerId && ($user = User::find($userId)) && !$user->stripe_id) {
            $user->forceFill(['stripe_id' => $customerId])->save();
        }

        $course = Course::find($courseId);
        if (!$course) return;

        $enrollment = Enrollment::firstOrNew(['user_id' => $userId, 'course_id' => $courseId]);
        $enrollment->status = 'active';
        $enrollment->stripe_subscription_id = $subId;
        $enrollment->enrolled_at = $enrollment->enrolled_at ?: now();
        $enrollment->canceled_at = null;
        $enrollment->save();
    }

    private function syncSubscription(array $sub): void
    {
        $subId = $sub['id'] ?? null;
        if (!$subId) return;
        $userId = (int) ($sub['metadata']['user_id'] ?? 0);
        $courseId = (int) ($sub['metadata']['course_id'] ?? 0);

        $enrollment = Enrollment::where('stripe_subscription_id', $subId)->first();
        if (!$enrollment && $userId && $courseId) {
            $enrollment = Enrollment::firstOrNew(['user_id' => $userId, 'course_id' => $courseId]);
            $enrollment->stripe_subscription_id = $subId;
        }
        if (!$enrollment) return;

        $oldStatus = $enrollment->status;
        $enrollment->status = match ($sub['status'] ?? '') {
            'active', 'trialing'              => 'active',
            'past_due', 'unpaid', 'incomplete' => 'past_due',
            'canceled', 'incomplete_expired'  => 'canceled',
            default                           => $enrollment->status,
        };
        if ($enrollment->status === 'active' && !$enrollment->enrolled_at) $enrollment->enrolled_at = now();
        if ($enrollment->status === 'canceled' && !$enrollment->canceled_at) $enrollment->canceled_at = now();

        // Mirror Stripe's billing period info so the UI can show "Adgang frem til X"
        // without an extra API call. cancel_at_period_end may toggle either way.
        if (isset($sub['current_period_end'])) {
            $enrollment->current_period_end = \Carbon\Carbon::createFromTimestamp((int) $sub['current_period_end']);
        }
        if (array_key_exists('cancel_at_period_end', $sub)) {
            $enrollment->cancel_at_period_end = (bool) $sub['cancel_at_period_end'];
        }
        $enrollment->save();

        if ($oldStatus !== 'past_due' && $enrollment->status === 'past_due') {
            $this->notifyPastDue($enrollment);
        }
    }

    private function cancelSubscription(array $sub): void
    {
        $subId = $sub['id'] ?? null;
        if (!$subId) return;
        $e = Enrollment::where('stripe_subscription_id', $subId)->first();
        if (!$e) return;
        $e->update(['status' => 'canceled', 'canceled_at' => now()]);
    }

    /**
     * Stripe fires this when a recurring charge fails. The subscription will
     * also flip to past_due via customer.subscription.updated, but we react
     * here too so the user is notified even if statuses arrive out of order.
     */
    private function handleInvoiceFailed(array $invoice): void
    {
        // Older API: invoice.subscription. Newer API (2024-09+): nested on parent.
        $subId = $invoice['subscription']
            ?? ($invoice['parent']['subscription_details']['subscription'] ?? null);
        if (!$subId) return;

        $enrollment = Enrollment::where('stripe_subscription_id', $subId)->first();
        if (!$enrollment) return;

        $oldStatus = $enrollment->status;
        if ($oldStatus !== 'past_due') {
            $enrollment->update(['status' => 'past_due']);
            $this->notifyPastDue($enrollment);
        }
    }

    /**
     * Send a one-time in-app + email notification when an enrollment transitions
     * to past_due. Guarded by the caller on (oldStatus !== 'past_due') so the
     * same failure episode doesn't notify twice.
     */
    private function notifyPastDue(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('user', 'course');
        $user = $enrollment->user;
        $course = $enrollment->course;
        if (!$user || !$course) return;

        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Betaling fejlede',
            'body' => 'Din betaling for "' . $course->title . '" gik ikke igennem. Opdater dit kort for at bevare adgangen.',
            'link' => route('profile.billing'),
            'course_id' => $course->id,
        ]);

        if ($user->email) {
            try {
                Mail::to($user->email)->queue(new PaymentFailedMail($user, $course));
            } catch (\Throwable) {
                // Don't fail the webhook on mail issues — the in-app + banner still surface it.
            }
        }
    }
}
