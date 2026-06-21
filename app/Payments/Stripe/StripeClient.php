<?php

namespace App\Payments\Stripe;

use App\Models\User;
use App\Support\StripeConfig;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Low-level Stripe REST client. In the rebuilt payment module Stripe is the
 * one-time fallback only — there are no Stripe subscriptions. Recurring
 * memberships go through MobilePay (see App\Payments\MobilePay).
 */
class StripeClient
{
    private const BASE = 'https://api.stripe.com/v1/';

    private static function request(string $method, string $path, array $payload = []): Response
    {
        $secret = StripeConfig::secret();
        if (! $secret) {
            throw new RuntimeException('Stripe is not configured.');
        }
        $req = Http::withToken($secret)->asForm()->acceptJson()->timeout(15);

        return match (strtoupper($method)) {
            'GET' => $req->get(self::BASE.$path, $payload),
            'POST' => $req->post(self::BASE.$path, $payload),
            'DELETE' => $req->delete(self::BASE.$path, $payload),
        };
    }

    private static function ok(Response $res): array
    {
        if (! $res->ok()) {
            $msg = $res->json('error.message') ?? ('HTTP '.$res->status());
            throw new RuntimeException('Stripe: '.$msg);
        }

        return $res->json();
    }

    /**
     * Reuse a user's Stripe customer or create one. Stripe customer id is
     * stored on the user row.
     */
    public static function ensureCustomer(User $user): string
    {
        if ($user->stripe_id) {
            return $user->stripe_id;
        }
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
     * One-time Checkout Session (mode=payment) for an arbitrary amount. Used for
     * floating bookings and the card fallback when a user enrolls without
     * MobilePay (each pays one month / one slot up front).
     */
    public static function createOneTimeCheckoutSession(
        User $user,
        int $amountCents,
        string $description,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        ?string $currency = null,
    ): array {
        if ($amountCents <= 0) {
            throw new RuntimeException('Amount must be positive.');
        }
        $customerId = self::ensureCustomer($user);
        $payload = [
            'mode' => 'payment',
            'customer' => $customerId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][price_data][currency]' => $currency ?: StripeConfig::currency(),
            'line_items[0][price_data][unit_amount]' => $amountCents,
            'line_items[0][price_data][product_data][name]' => $description,
            'line_items[0][quantity]' => 1,
        ];
        foreach ($metadata as $k => $v) {
            $payload['metadata['.$k.']'] = (string) $v;
            $payload['payment_intent_data[metadata]['.$k.']'] = (string) $v;
        }

        return self::ok(self::request('POST', 'checkout/sessions', $payload));
    }

    /** Fetch a Checkout Session by id — used to reconcile on return from Stripe. */
    public static function retrieveCheckoutSession(string $sessionId): array
    {
        return self::ok(self::request('GET', 'checkout/sessions/'.$sessionId));
    }

    /**
     * Refund a one-time charge by payment_intent id. Returns the refund payload,
     * or null when the payment_intent id is empty.
     */
    public static function refundPaymentIntent(string $paymentIntentId, ?int $amountCents = null): ?array
    {
        if ($paymentIntentId === '') {
            return null;
        }
        $payload = ['payment_intent' => $paymentIntentId];
        if ($amountCents !== null) {
            $payload['amount'] = $amountCents;
        }

        return self::ok(self::request('POST', 'refunds', $payload));
    }
}
