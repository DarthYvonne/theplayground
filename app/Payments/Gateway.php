<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentProvider;
use App\Payments\Contracts\RecurringProvider;
use InvalidArgumentException;

/**
 * Entry point for the payment module. Decouples controllers from any single PSP:
 * they ask the gateway for "the provider to use for a membership" or "for a
 * one-time payment" and the gateway applies the primary/fallback policy.
 *
 * Policy: MobilePay is primary (one-time + recurring); Stripe is the one-time
 * fallback. When MobilePay is not configured (e.g. credentials not yet issued)
 * one-time payments transparently fall back to Stripe and recurring is
 * reported as unavailable.
 */
class Gateway
{
    /** @param array<string, PaymentProvider> $providers keyed by provider key */
    public function __construct(
        private readonly array $providers,
        private readonly string $primaryKey,
        private readonly string $fallbackKey,
    ) {}

    public function provider(string $key): PaymentProvider
    {
        return $this->providers[$key]
            ?? throw new InvalidArgumentException("Unknown payment provider: {$key}");
    }

    public function fallback(): PaymentProvider
    {
        return $this->provider($this->fallbackKey);
    }

    /** The recurring-capable provider, or null when it isn't configured. */
    public function recurring(): ?RecurringProvider
    {
        $primary = $this->providers[$this->primaryKey] ?? null;

        return ($primary instanceof RecurringProvider && $primary->isConfigured())
            ? $primary
            : null;
    }

    public function recurringAvailable(): bool
    {
        return $this->recurring() !== null;
    }

    /** Provider to use for a one-time payment: primary if configured, else the fallback. */
    public function oneTime(): PaymentProvider
    {
        $primary = $this->providers[$this->primaryKey] ?? null;

        return ($primary && $primary->isConfigured()) ? $primary : $this->fallback();
    }
}
