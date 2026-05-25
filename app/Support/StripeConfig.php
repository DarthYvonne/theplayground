<?php

namespace App\Support;

class StripeConfig
{
    /** Publishable key (safe to expose in browser). */
    public static function publicKey(): ?string
    {
        return config('services.stripe.key') ?: null;
    }

    /** Secret key. NEVER expose. */
    public static function secret(): ?string
    {
        return config('services.stripe.secret') ?: null;
    }

    public static function webhookSecret(): ?string
    {
        return config('services.stripe.webhook_secret') ?: null;
    }

    public static function currency(): string
    {
        return config('services.stripe.currency') ?: 'dkk';
    }

    public static function isConfigured(): bool
    {
        return !empty(self::secret());
    }
}
