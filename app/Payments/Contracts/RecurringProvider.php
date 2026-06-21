<?php

namespace App\Payments\Contracts;

use App\Payments\Dto\ChargeResult;
use App\Payments\Dto\MembershipRequest;
use App\Payments\Dto\RedirectResult;
use DateTimeInterface;

/**
 * A provider that supports recurring memberships via a standing agreement the
 * user approves once. Unlike a card subscription, the merchant (us) must create
 * each periodic charge itself — see the payments:charge-due scheduler.
 */
interface RecurringProvider extends PaymentProvider
{
    /** Create a draft agreement; returns the approval redirect + agreement reference. */
    public function startMembership(MembershipRequest $request): RedirectResult;

    /**
     * Stop an agreement. The caller keeps access until current_period_end; we
     * simply stop creating new charges.
     */
    public function stopMembership(string $agreementReference): void;

    /** Create one period's charge against an active agreement, due on $dueDate. */
    public function chargeMembership(
        string $agreementReference,
        int $amountCents,
        DateTimeInterface $dueDate,
        string $description,
        ?string $currency = null,
    ): ChargeResult;

    /** Normalised agreement status: 'pending' | 'active' | 'stopped' | 'expired'. */
    public function getMembershipStatus(string $agreementReference): string;
}
