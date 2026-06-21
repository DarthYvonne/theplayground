<?php

namespace App\Payments\Dto;

use App\Models\User;

/** A request to start a single, non-recurring payment. */
final class OneTimeRequest
{
    public function __construct(
        public readonly User $user,
        public readonly int $amountCents,
        public readonly string $description,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        /** Arbitrary key/value pairs echoed back on webhooks (string-coerced). */
        public readonly array $metadata = [],
        public readonly ?string $currency = null,
    ) {}
}
