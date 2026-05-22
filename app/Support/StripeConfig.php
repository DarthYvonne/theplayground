<?php

namespace App\Support;

use App\Models\Setting;

class StripeConfig
{
    /** Publishable key (safe to expose in browser). DB overrides .env. */
    public static function publicKey(): ?string
    {
        return Setting::get('stripe_key') ?: env('STRIPE_KEY') ?: null;
    }

    /** Secret key. NEVER expose. DB overrides .env. */
    public static function secret(): ?string
    {
        return Setting::get('stripe_secret') ?: env('STRIPE_SECRET') ?: null;
    }

    public static function webhookSecret(): ?string
    {
        return Setting::get('stripe_webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET') ?: null;
    }

    public static function currency(): string
    {
        return Setting::get('stripe_currency') ?: env('CASHIER_CURRENCY', 'eur');
    }

    public static function isConfigured(): bool
    {
        return !empty(self::secret());
    }
}
