<?php

namespace App\Support;

class StripeConfig
{
    /** Publishable key (safe to expose in browser). */
    public static function publicKey(): ?string
    {
        return env('STRIPE_KEY') ?: null;
    }

    /** Secret key. NEVER expose. */
    public static function secret(): ?string
    {
        return env('STRIPE_SECRET') ?: null;
    }

    public static function webhookSecret(): ?string
    {
        return env('STRIPE_WEBHOOK_SECRET') ?: null;
    }

    public static function currency(): string
    {
        return env('CASHIER_CURRENCY', 'eur');
    }

    public static function isConfigured(): bool
    {
        return !empty(self::secret());
    }
}
