<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider policy
    |--------------------------------------------------------------------------
    | MobilePay is the primary rail (one-time + recurring memberships); Stripe
    | is the one-time fallback. Values survive `config:cache` (read via config()).
    */
    'primary' => env('PAYMENTS_PRIMARY', 'mobilepay'),
    'fallback' => env('PAYMENTS_FALLBACK', 'stripe'),

    // Minor-unit currency used across providers (lowercase; clients up-case as needed).
    'currency' => env('PAYMENTS_CURRENCY', 'dkk'),

    // Where the recurring run-summary email is sent. Falls back to the first
    // owner user when empty (see RecurringRunSummaryMail / the charge-due command).
    'owner_email' => env('PAYMENTS_OWNER_EMAIL'),

    'recurring' => [
        // How many days before a membership's renewal date we create the charge.
        // MobilePay requires charges to be created at least 1 day in advance.
        'lead_days' => (int) env('PAYMENTS_LEAD_DAYS', 2),
        // How long MobilePay keeps retrying a failed charge (max 14).
        'retry_days' => (int) env('PAYMENTS_RETRY_DAYS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | MobilePay (Vipps MobilePay) — direct API
    |--------------------------------------------------------------------------
    | Dormant until credentials are issued: with `enabled` false (or creds
    | missing) the gateway treats MobilePay as unconfigured and uses Stripe.
    */
    'mobilepay' => [
        'enabled' => env('MOBILEPAY_ENABLED', false),
        // Test: https://apitest.vippsmobilepay.com — Prod: https://api.vippsmobilepay.com
        'base_url' => env('MOBILEPAY_BASE_URL', 'https://apitest.vippsmobilepay.com'),
        'client_id' => env('MOBILEPAY_CLIENT_ID'),
        'client_secret' => env('MOBILEPAY_CLIENT_SECRET'),
        'subscription_key' => env('MOBILEPAY_SUBSCRIPTION_KEY'),
        'merchant_serial_number' => env('MOBILEPAY_MSN'),
        // Used for the required Vipps-System-Name/-Version headers.
        'system_name' => env('APP_NAME', 'Playground'),
        'system_version' => env('MOBILEPAY_SYSTEM_VERSION', '1.0.0'),
        // Optional shared secret used to validate inbound webhooks.
        'webhook_secret' => env('MOBILEPAY_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe — one-time fallback only (no subscriptions)
    |--------------------------------------------------------------------------
    | Credentials live in config/services.php ('stripe') and are read through
    | App\Support\StripeConfig; mirrored here only for the enabled flag.
    */
    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', true),
    ],

];
