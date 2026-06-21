<?php

namespace App\Payments\Stripe;

use App\Payments\Contracts\PaymentProvider;
use App\Payments\Dto\ChargeResult;
use App\Payments\Dto\OneTimeRequest;
use App\Payments\Dto\RedirectResult;
use App\Support\StripeConfig;

/** Stripe adapter — one-time payments only (the fallback rail). */
class StripeProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return (bool) config('payments.stripe.enabled', true) && StripeConfig::isConfigured();
    }

    public function startOneTime(OneTimeRequest $request): RedirectResult
    {
        $session = StripeClient::createOneTimeCheckoutSession(
            $request->user,
            $request->amountCents,
            $request->description,
            $request->successUrl,
            $request->cancelUrl,
            $request->metadata,
            $request->currency,
        );

        return new RedirectResult($session['url'], $session['id'], $this->key());
    }

    public function getPaymentStatus(string $reference): ChargeResult
    {
        // $reference is a Checkout Session id; we resolve it to the payment_intent
        // (the refundable charge reference) and a normalised status.
        $s = StripeClient::retrieveCheckoutSession($reference);
        $captured = ($s['status'] ?? '') === 'complete' && ($s['payment_status'] ?? '') === 'paid';
        $pi = $s['payment_intent'] ?? '';
        $chargeRef = is_array($pi) ? ($pi['id'] ?? $reference) : ($pi ?: $reference);

        return new ChargeResult(
            (string) $chargeRef,
            $captured ? ChargeResult::CAPTURED : ChargeResult::PENDING,
            (int) ($s['amount_total'] ?? 0),
            $this->key(),
        );
    }

    public function refund(string $reference, ?int $amountCents = null): void
    {
        StripeClient::refundPaymentIntent($reference, $amountCents);
    }
}
