<?php

namespace App\Payments\Contracts;

use App\Payments\Dto\ChargeResult;
use App\Payments\Dto\OneTimeRequest;
use App\Payments\Dto\RedirectResult;

/**
 * A payment provider that can take a single, non-recurring payment. Every
 * provider implements this; recurring-capable providers also implement
 * {@see RecurringProvider}.
 */
interface PaymentProvider
{
    /** Stable key used in config, the payments ledger and webhooks ('mobilepay' | 'stripe'). */
    public function key(): string;

    /** Whether credentials are present so this provider can actually be used. */
    public function isConfigured(): bool;

    /** Start a one-time payment; returns where to redirect the user. */
    public function startOneTime(OneTimeRequest $request): RedirectResult;

    /** Look up the normalised status of a one-time payment by its provider reference. */
    public function getPaymentStatus(string $reference): ChargeResult;

    /** Refund a captured one-time payment, fully (null) or partially. */
    public function refund(string $reference, ?int $amountCents = null): void;
}
