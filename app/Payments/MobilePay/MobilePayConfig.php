<?php

namespace App\Payments\MobilePay;

/** Typed accessor over config/payments.php → 'mobilepay'. */
class MobilePayConfig
{
    public static function enabled(): bool
    {
        return (bool) config('payments.mobilepay.enabled', false);
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('payments.mobilepay.base_url'), '/');
    }

    public static function clientId(): ?string
    {
        return config('payments.mobilepay.client_id') ?: null;
    }

    public static function clientSecret(): ?string
    {
        return config('payments.mobilepay.client_secret') ?: null;
    }

    public static function subscriptionKey(): ?string
    {
        return config('payments.mobilepay.subscription_key') ?: null;
    }

    public static function merchantSerialNumber(): ?string
    {
        return config('payments.mobilepay.merchant_serial_number') ?: null;
    }

    public static function systemName(): string
    {
        return (string) config('payments.mobilepay.system_name', 'Playground');
    }

    public static function systemVersion(): string
    {
        return (string) config('payments.mobilepay.system_version', '1.0.0');
    }

    public static function webhookSecret(): ?string
    {
        return config('payments.mobilepay.webhook_secret') ?: null;
    }

    /** Up-cased currency code for the MobilePay API (e.g. DKK). */
    public static function currency(): string
    {
        return strtoupper((string) config('payments.currency', 'dkk'));
    }

    /** All credentials present and the feature switched on. */
    public static function isConfigured(): bool
    {
        return self::enabled()
            && self::clientId()
            && self::clientSecret()
            && self::subscriptionKey()
            && self::merchantSerialNumber();
    }
}
