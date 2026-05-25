<?php

namespace App\Support;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeService
{
    private const BASE = 'https://api.stripe.com/v1/';

    private static function request(string $method, string $path, array $payload = []): Response
    {
        $secret = StripeConfig::secret();
        if (!$secret) throw new RuntimeException('Stripe is not configured.');
        $req = Http::withToken($secret)->asForm()->acceptJson()->timeout(15);
        return match (strtoupper($method)) {
            'GET'    => $req->get(self::BASE . $path, $payload),
            'POST'   => $req->post(self::BASE . $path, $payload),
            'DELETE' => $req->delete(self::BASE . $path, $payload),
        };
    }

    private static function ok(Response $res): array
    {
        if (!$res->ok()) {
            $msg = $res->json('error.message') ?? ('HTTP ' . $res->status());
            throw new RuntimeException('Stripe: ' . $msg);
        }
        return $res->json();
    }

    public static function createProduct(string $name, ?string $description = null): array
    {
        return self::ok(self::request('POST', 'products', array_filter([
            'name' => $name,
            'description' => $description ? mb_substr($description, 0, 500) : null,
        ])));
    }

    public static function updateProduct(string $productId, string $name, ?string $description = null): array
    {
        return self::ok(self::request('POST', 'products/' . $productId, array_filter([
            'name' => $name,
            'description' => $description ? mb_substr($description, 0, 500) : null,
        ])));
    }

    public static function archiveProduct(string $productId): void
    {
        // Soft-archive: products with active subscriptions cannot be deleted.
        try { self::ok(self::request('POST', 'products/' . $productId, ['active' => 'false'])); }
        catch (\Throwable) {}
    }

    public static function createRecurringMonthlyPrice(string $productId, int $cents, ?string $currency = null): array
    {
        return self::ok(self::request('POST', 'prices', [
            'product' => $productId,
            'currency' => $currency ?: StripeConfig::currency(),
            'unit_amount' => $cents,
            'recurring' => ['interval' => 'month'],
        ]));
    }

    public static function createOneTimePrice(string $productId, int $cents, ?string $currency = null): array
    {
        return self::ok(self::request('POST', 'prices', [
            'product' => $productId,
            'currency' => $currency ?: StripeConfig::currency(),
            'unit_amount' => $cents,
        ]));
    }

    public static function createOneTimeCheckoutSession(User $user, string $priceId, string $successUrl, string $cancelUrl, array $metadata = []): array
    {
        $customerId = self::ensureCustomer($user);
        $payload = [
            'mode' => 'payment',
            'customer' => $customerId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
        ];
        foreach ($metadata as $k => $v) {
            $payload['metadata[' . $k . ']'] = (string) $v;
            $payload['payment_intent_data[metadata][' . $k . ']'] = (string) $v;
        }
        return self::ok(self::request('POST', 'checkout/sessions', $payload));
    }

    public static function archivePrice(string $priceId): void
    {
        try { self::ok(self::request('POST', 'prices/' . $priceId, ['active' => 'false'])); }
        catch (\Throwable) {}
    }

    /**
     * Reuse a user's Stripe customer or create one. Stripe customer id is
     * stored on the user row.
     */
    public static function ensureCustomer(User $user): string
    {
        if ($user->stripe_id) return $user->stripe_id;
        $data = self::ok(self::request('POST', 'customers', array_filter([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => null, // form encoding will skip null
            'metadata[user_id]' => (string) $user->id,
        ])));
        $user->forceFill(['stripe_id' => $data['id']])->save();
        return $data['id'];
    }

    /**
     * One-time MobilePay payment for one month of access to a course. MobilePay
     * is not supported in Stripe subscription mode, so each renewal is a new
     * Checkout Session. The webhook activates the enrollment for one month;
     * the user must re-pay to continue beyond that.
     */
    public static function createMobilePayCheckoutSession(User $user, Course $course, string $successUrl, string $cancelUrl): array
    {
        if ($course->price_cents <= 0) throw new RuntimeException('Course has no price.');
        $customerId = self::ensureCustomer($user);
        return self::ok(self::request('POST', 'checkout/sessions', [
            'mode' => 'payment',
            'customer' => $customerId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'payment_method_types[0]' => 'mobilepay',
            'line_items[0][price_data][currency]' => StripeConfig::currency(),
            'line_items[0][price_data][unit_amount]' => $course->price_cents,
            'line_items[0][price_data][product_data][name]' => $course->title . ' — 1 måned',
            'line_items[0][quantity]' => 1,
            'metadata[user_id]' => (string) $user->id,
            'metadata[course_id]' => (string) $course->id,
            'metadata[payment_method]' => 'mobilepay',
            'payment_intent_data[metadata][user_id]' => (string) $user->id,
            'payment_intent_data[metadata][course_id]' => (string) $course->id,
            'payment_intent_data[metadata][payment_method]' => 'mobilepay',
        ]));
    }

    public static function createCheckoutSession(User $user, Course $course, string $successUrl, string $cancelUrl): array
    {
        if (!$course->stripe_price_id) throw new RuntimeException('Course has no Stripe price. Re-save the course to provision it.');
        $customerId = self::ensureCustomer($user);
        return self::ok(self::request('POST', 'checkout/sessions', [
            'mode' => 'subscription',
            'customer' => $customerId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][price]' => $course->stripe_price_id,
            'line_items[0][quantity]' => 1,
            'metadata[user_id]' => (string) $user->id,
            'metadata[course_id]' => (string) $course->id,
            'subscription_data[metadata][user_id]' => (string) $user->id,
            'subscription_data[metadata][course_id]' => (string) $course->id,
        ]));
    }

    /** Fetch a Checkout Session by id — used to reconcile enrollment immediately on return. */
    public static function retrieveCheckoutSession(string $sessionId): array
    {
        return self::ok(self::request('GET', 'checkout/sessions/' . $sessionId));
    }

    /** Fetch a Subscription — used after checkout to read current_period_end. */
    public static function retrieveSubscription(string $subscriptionId): array
    {
        return self::ok(self::request('GET', 'subscriptions/' . $subscriptionId));
    }

    /**
     * Cancel a subscription at the end of its current billing period. The user keeps
     * access until that date and is not charged again. Pass $cancel=false to undo.
     */
    public static function cancelSubscriptionAtPeriodEnd(string $subscriptionId, bool $cancel = true): array
    {
        return self::ok(self::request('POST', 'subscriptions/' . $subscriptionId, [
            'cancel_at_period_end' => $cancel ? 'true' : 'false',
        ]));
    }

    /**
     * Refund a one-time charge by payment_intent id. Returns the refund payload,
     * or throws RuntimeException on Stripe error. No-ops (returns null) if the
     * payment_intent id is empty.
     */
    public static function refundPaymentIntent(string $paymentIntentId, ?int $amountCents = null): ?array
    {
        if ($paymentIntentId === '') return null;
        $payload = ['payment_intent' => $paymentIntentId];
        if ($amountCents !== null) $payload['amount'] = $amountCents;
        return self::ok(self::request('POST', 'refunds', $payload));
    }

    public static function customerPortalUrl(User $user, string $returnUrl): ?string
    {
        if (!$user->stripe_id) return null;
        try {
            $data = self::ok(self::request('POST', 'billing_portal/sessions', [
                'customer' => $user->stripe_id,
                'return_url' => $returnUrl,
            ]));
            return $data['url'] ?? null;
        } catch (\Throwable) { return null; }
    }
}
