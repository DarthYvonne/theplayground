<?php

namespace App\Payments\Dto;

use App\Models\User;

/**
 * A request to start a recurring membership: the user approves a standing
 * agreement once, after which we charge them once per period via the scheduler.
 */
final class MembershipRequest
{
    public function __construct(
        public readonly User $user,
        public readonly int $amountCents,
        public readonly string $productName,
        public readonly string $successUrl,   // where the provider returns the user after approval
        public readonly string $cancelUrl,
        /** Arbitrary key/value pairs echoed back on webhooks (string-coerced). */
        public readonly array $metadata = [],
        public readonly ?string $currency = null,
    ) {}
}
