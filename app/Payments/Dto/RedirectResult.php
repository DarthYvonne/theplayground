<?php

namespace App\Payments\Dto;

/**
 * Returned by a provider when the user must be redirected to a hosted page to
 * complete a payment or approve a recurring agreement.
 */
final class RedirectResult
{
    public function __construct(
        public readonly string $url,        // where to send the user
        public readonly string $reference,  // provider reference (checkout session / agreement / payment id)
        public readonly string $provider,   // 'mobilepay' | 'stripe'
    ) {}
}
