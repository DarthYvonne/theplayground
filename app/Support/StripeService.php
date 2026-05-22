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
