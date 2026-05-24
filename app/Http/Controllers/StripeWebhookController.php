<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\StripeConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
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

        match ($event['type'] ?? '') {
            'checkout.session.completed'      => $this->handleCheckoutCompleted($event['data']['object'] ?? []),
            'customer.subscription.created',
            'customer.subscription.updated'   => $this->syncSubscription($event['data']['object'] ?? []),
            'customer.subscription.deleted'   => $this->cancelSubscription($event['data']['object'] ?? []),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    /** Verify a Stripe webhook signature header (t=…,v1=…). */
    private function verifySignature(string $payload, ?string $header, string $secret): bool
    {
        if (!$header) return false;
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, null);
            $parts[$k][] = $v;
        }
        if (empty($parts['t'][0]) || empty($parts['v1'])) return false;
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

        $enrollment->status = match ($sub['status'] ?? '') {
            'active', 'trialing'             => 'active',
            'past_due', 'unpaid', 'incomplete' => 'past_due',
            'canceled', 'incomplete_expired'  => 'canceled',
            default                           => $enrollment->status,
        };
        if ($enrollment->status === 'active' && !$enrollment->enrolled_at) $enrollment->enrolled_at = now();
        if ($enrollment->status === 'canceled' && !$enrollment->canceled_at) $enrollment->canceled_at = now();
        $enrollment->save();
    }

    private function cancelSubscription(array $sub): void
    {
        $subId = $sub['id'] ?? null;
        if (!$subId) return;
        $e = Enrollment::where('stripe_subscription_id', $subId)->first();
        if (!$e) return;
        $e->update(['status' => 'canceled', 'canceled_at' => now()]);
    }
}
