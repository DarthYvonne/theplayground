<?php

namespace App\Payments\MobilePay;

use App\Payments\Contracts\RecurringProvider;
use App\Payments\Dto\ChargeResult;
use App\Payments\Dto\MembershipRequest;
use App\Payments\Dto\OneTimeRequest;
use App\Payments\Dto\RedirectResult;
use DateTimeInterface;
use Illuminate\Support\Str;
use RuntimeException;

/** MobilePay adapter — one-time (ePayment) + recurring memberships (Recurring API). */
class MobilePayProvider implements RecurringProvider
{
    public function __construct(private readonly MobilePayClient $client) {}

    public function key(): string
    {
        return 'mobilepay';
    }

    public function isConfigured(): bool
    {
        return MobilePayConfig::isConfigured();
    }

    /* --------------------------------------------------------- one-time -- */

    public function startOneTime(OneTimeRequest $request): RedirectResult
    {
        $reference = 'pg-'.Str::lower(Str::random(24));
        $payment = $this->client->createPayment(
            $request->amountCents,
            $reference,
            $request->successUrl,
            $request->description,
        );
        $url = $payment['redirectUrl'] ?? null;
        if (! $url) {
            throw new RuntimeException('MobilePay: no redirectUrl returned.');
        }

        return new RedirectResult($url, $reference, $this->key());
    }

    public function getPaymentStatus(string $reference): ChargeResult
    {
        $p = $this->client->getPayment($reference);
        $state = $p['state'] ?? '';
        $value = (int) ($p['amount']['value'] ?? 0);
        $captured = (int) ($p['aggregate']['capturedAmount']['value'] ?? 0);

        if ($captured > 0) {
            return new ChargeResult($reference, ChargeResult::CAPTURED, $value, $this->key());
        }
        if ($state === 'AUTHORIZED') {
            // Reserve → capture, mirroring Stripe's immediate capture for one-off payments.
            $this->client->capturePayment($reference, $value);

            return new ChargeResult($reference, ChargeResult::CAPTURED, $value, $this->key());
        }

        $status = match ($state) {
            'ABORTED', 'EXPIRED', 'TERMINATED' => ChargeResult::FAILED,
            default => ChargeResult::PENDING,
        };

        return new ChargeResult($reference, $status, $value, $this->key());
    }

    public function refund(string $reference, ?int $amountCents = null): void
    {
        if ($amountCents === null) {
            $p = $this->client->getPayment($reference);
            $amountCents = (int) ($p['aggregate']['capturedAmount']['value'] ?? $p['amount']['value'] ?? 0);
        }
        if ($amountCents <= 0) {
            return;
        }
        $this->client->refundPayment($reference, $amountCents);
    }

    /* -------------------------------------------------------- recurring -- */

    public function startMembership(MembershipRequest $request): RedirectResult
    {
        // Include an initial charge so the first month is paid at sign-up; the
        // scheduler charges subsequent months ahead of each renewal.
        $agreement = $this->client->createAgreement(
            amount: $request->amountCents,
            productName: $request->productName,
            merchantRedirectUrl: $request->successUrl,
            merchantAgreementUrl: route('profile.billing'),
            initialChargeAmount: $request->amountCents,
            initialChargeDescription: $request->productName,
        );

        $id = $agreement['agreementId'] ?? null;
        $url = $agreement['vippsConfirmationUrl'] ?? null;
        if (! $id || ! $url) {
            throw new RuntimeException('MobilePay: incomplete agreement response.');
        }

        return new RedirectResult($url, $id, $this->key());
    }

    public function stopMembership(string $agreementReference): void
    {
        $this->client->stopAgreement($agreementReference);
    }

    public function chargeMembership(
        string $agreementReference,
        int $amountCents,
        DateTimeInterface $dueDate,
        string $description,
        ?string $currency = null,
    ): ChargeResult {
        $charge = $this->client->createCharge(
            $agreementReference,
            $amountCents,
            $dueDate,
            $description,
            (int) config('payments.recurring.retry_days', 5),
        );

        $chargeId = $charge['chargeId'] ?? ($charge['id'] ?? '');

        return new ChargeResult((string) $chargeId, ChargeResult::PENDING, $amountCents, $this->key());
    }

    public function getMembershipStatus(string $agreementReference): string
    {
        $agreement = $this->client->getAgreement($agreementReference);

        return match ($agreement['status'] ?? '') {
            'ACTIVE' => 'active',
            'STOPPED' => 'stopped',
            'EXPIRED' => 'expired',
            default => 'pending',
        };
    }
}
