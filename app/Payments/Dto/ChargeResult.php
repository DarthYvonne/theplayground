<?php

namespace App\Payments\Dto;

/**
 * Normalised result of a one-time payment or a recurring charge, regardless of
 * which provider produced it. Status is mapped to our own vocabulary so callers
 * never branch on provider-specific strings.
 */
final class ChargeResult
{
    public const PENDING = 'pending';

    public const RESERVED = 'reserved';

    public const CAPTURED = 'captured';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $reference,   // provider charge / payment id
        public readonly string $status,      // one of the constants above
        public readonly int $amountCents,
        public readonly string $provider,
    ) {}

    public function isCaptured(): bool
    {
        return $this->status === self::CAPTURED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }
}
