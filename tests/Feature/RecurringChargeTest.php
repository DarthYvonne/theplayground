<?php

namespace Tests\Feature;

use App\Mail\RecurringRunSummaryMail;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Contracts\RecurringProvider;
use App\Payments\Dto\ChargeResult;
use App\Payments\Dto\MembershipRequest;
use App\Payments\Dto\OneTimeRequest;
use App\Payments\Dto\RedirectResult;
use App\Payments\Gateway;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecurringChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_due_creates_one_idempotent_charge_per_period(): void
    {
        Mail::fake();
        config(['payments.owner_email' => 'owner@test.dk']);

        $fake = $this->bindFakeRecurringGateway();

        $enrollment = $this->dueMembership();

        $this->artisan('payments:charge-due')->assertSuccessful();

        $this->assertSame(1, Payment::count());
        $payment = Payment::first();
        $this->assertSame(Payment::KIND_RECURRING, $payment->kind);
        $this->assertSame(Payment::RESERVED, $payment->status);
        $this->assertSame('chg_fake', $payment->external_id);
        $this->assertSame(1, $fake->charges, 'provider should be called exactly once');

        // Running again in the same period must not create a second charge.
        $this->artisan('payments:charge-due')->assertSuccessful();
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, $fake->charges);

        Mail::assertQueued(RecurringRunSummaryMail::class);
    }

    public function test_charge_due_skips_cancelled_and_card_memberships(): void
    {
        $this->bindFakeRecurringGateway();

        // cancel_at_period_end => skipped
        $this->dueMembership(['cancel_at_period_end' => true]);
        // stripe/card provider => skipped (not a MobilePay agreement)
        $this->dueMembership(['provider' => 'stripe', 'mobilepay_agreement_id' => null]);

        $this->artisan('payments:charge-due')->assertSuccessful();
        $this->assertSame(0, Payment::count());
    }

    private function dueMembership(array $overrides = []): Enrollment
    {
        $user = User::factory()->create();
        $course = Course::create([
            'title' => 'Yoga',
            'description' => 'Beskrivelse',
            'price_cents' => 12000,
            'max_participants' => 20,
            'is_active' => true,
        ]);

        return Enrollment::create(array_merge([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'provider' => 'mobilepay',
            'mobilepay_agreement_id' => 'agr_'.$user->id,
            'enrolled_at' => now()->subMonth(),
            'current_period_end' => now()->addDay(),
            'cancel_at_period_end' => false,
        ], $overrides));
    }

    private function bindFakeRecurringGateway(): object
    {
        $provider = new class implements RecurringProvider
        {
            public int $charges = 0;

            public function key(): string
            {
                return 'mobilepay';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function startOneTime(OneTimeRequest $r): RedirectResult
            {
                return new RedirectResult('u', 'r', 'mobilepay');
            }

            public function getPaymentStatus(string $ref): ChargeResult
            {
                return new ChargeResult($ref, ChargeResult::CAPTURED, 0, 'mobilepay');
            }

            public function refund(string $ref, ?int $amt = null): void {}

            public function startMembership(MembershipRequest $r): RedirectResult
            {
                return new RedirectResult('u', 'agr', 'mobilepay');
            }

            public function stopMembership(string $ref): void {}

            public function chargeMembership(string $ref, int $amt, DateTimeInterface $due, string $desc, ?string $cur = null): ChargeResult
            {
                $this->charges++;

                return new ChargeResult('chg_fake', ChargeResult::PENDING, $amt, 'mobilepay');
            }

            public function getMembershipStatus(string $ref): string
            {
                return 'active';
            }
        };

        $this->app->instance(Gateway::class, new Gateway(['mobilepay' => $provider], 'mobilepay', 'stripe'));

        return $provider;
    }
}
