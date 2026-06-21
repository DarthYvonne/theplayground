<?php

namespace Tests\Unit;

use App\Payments\Contracts\PaymentProvider;
use App\Payments\Contracts\RecurringProvider;
use App\Payments\Dto\ChargeResult;
use App\Payments\Dto\MembershipRequest;
use App\Payments\Dto\OneTimeRequest;
use App\Payments\Dto\RedirectResult;
use App\Payments\Gateway;
use DateTimeInterface;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    public function test_recurring_returns_primary_when_configured(): void
    {
        $gateway = $this->gateway(mobilePayConfigured: true, stripeConfigured: true);

        $this->assertTrue($gateway->recurringAvailable());
        $this->assertSame('mobilepay', $gateway->recurring()->key());
        $this->assertSame('mobilepay', $gateway->oneTime()->key());
    }

    public function test_falls_back_to_stripe_when_mobilepay_unconfigured(): void
    {
        $gateway = $this->gateway(mobilePayConfigured: false, stripeConfigured: true);

        $this->assertFalse($gateway->recurringAvailable());
        $this->assertNull($gateway->recurring());
        $this->assertSame('stripe', $gateway->oneTime()->key());
        $this->assertSame('stripe', $gateway->fallback()->key());
    }

    public function test_unknown_provider_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->gateway()->provider('paypal');
    }

    private function gateway(bool $mobilePayConfigured = true, bool $stripeConfigured = true): Gateway
    {
        return new Gateway(
            [
                'mobilepay' => $this->recurringProvider('mobilepay', $mobilePayConfigured),
                'stripe' => $this->oneTimeProvider('stripe', $stripeConfigured),
            ],
            'mobilepay',
            'stripe',
        );
    }

    private function oneTimeProvider(string $key, bool $configured): PaymentProvider
    {
        return new class($key, $configured) implements PaymentProvider
        {
            public function __construct(private string $k, private bool $c) {}

            public function key(): string
            {
                return $this->k;
            }

            public function isConfigured(): bool
            {
                return $this->c;
            }

            public function startOneTime(OneTimeRequest $r): RedirectResult
            {
                return new RedirectResult('u', 'r', $this->k);
            }

            public function getPaymentStatus(string $ref): ChargeResult
            {
                return new ChargeResult($ref, ChargeResult::CAPTURED, 0, $this->k);
            }

            public function refund(string $ref, ?int $amt = null): void {}
        };
    }

    private function recurringProvider(string $key, bool $configured): RecurringProvider
    {
        return new class($key, $configured) implements RecurringProvider
        {
            public function __construct(private string $k, private bool $c) {}

            public function key(): string
            {
                return $this->k;
            }

            public function isConfigured(): bool
            {
                return $this->c;
            }

            public function startOneTime(OneTimeRequest $r): RedirectResult
            {
                return new RedirectResult('u', 'r', $this->k);
            }

            public function getPaymentStatus(string $ref): ChargeResult
            {
                return new ChargeResult($ref, ChargeResult::CAPTURED, 0, $this->k);
            }

            public function refund(string $ref, ?int $amt = null): void {}

            public function startMembership(MembershipRequest $r): RedirectResult
            {
                return new RedirectResult('u', 'agr', $this->k);
            }

            public function stopMembership(string $ref): void {}

            public function chargeMembership(string $ref, int $amt, DateTimeInterface $due, string $desc, ?string $cur = null): ChargeResult
            {
                return new ChargeResult('chg', ChargeResult::PENDING, $amt, $this->k);
            }

            public function getMembershipStatus(string $ref): string
            {
                return 'active';
            }
        };
    }
}
