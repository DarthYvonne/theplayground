<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobilePayWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_captured_extends_access_and_dedupes(): void
    {
        [$enrollment, $payment] = $this->membershipWithPendingCharge();
        $before = $enrollment->current_period_end->copy();

        $event = [
            'id' => 'evt_1',
            'name' => 'recurring.charge.captured.v1',
            'agreementId' => 'agr_1',
            'chargeId' => 'chg_1',
            'status' => 'CHARGED',
        ];

        $this->postJson('/mobilepay/webhook', $event)->assertOk();

        $payment->refresh();
        $enrollment->refresh();
        $this->assertSame(Payment::CAPTURED, $payment->status);
        $this->assertTrue($enrollment->current_period_end->gt($before), 'access should be extended');
        $extendedTo = $enrollment->current_period_end->copy();

        // Replaying the same event id must be a no-op.
        $this->postJson('/mobilepay/webhook', $event)
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        $enrollment->refresh();
        $this->assertEquals($extendedTo->timestamp, $enrollment->current_period_end->timestamp);
        $this->assertSame(1, \DB::table('webhook_events')->where('provider', 'mobilepay')->count());
    }

    public function test_charge_failed_marks_past_due(): void
    {
        [$enrollment, $payment] = $this->membershipWithPendingCharge();

        $this->postJson('/mobilepay/webhook', [
            'id' => 'evt_fail',
            'name' => 'recurring.charge.failed.v1',
            'agreementId' => 'agr_1',
            'chargeId' => 'chg_1',
            'status' => 'FAILED',
        ])->assertOk();

        $this->assertSame('past_due', $enrollment->fresh()->status);
        $this->assertSame(Payment::FAILED, $payment->fresh()->status);
    }

    /** @return array{0: Enrollment, 1: Payment} */
    private function membershipWithPendingCharge(): array
    {
        $user = User::factory()->create();
        $course = Course::create([
            'title' => 'Crossfit',
            'description' => 'Beskrivelse',
            'price_cents' => 15000,
            'max_participants' => 20,
            'is_active' => true,
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'provider' => 'mobilepay',
            'mobilepay_agreement_id' => 'agr_1',
            'enrolled_at' => now()->subMonth(),
            'current_period_end' => now()->addDays(3),
            'cancel_at_period_end' => false,
        ]);
        $payment = Payment::create([
            'user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'provider' => 'mobilepay',
            'kind' => Payment::KIND_RECURRING,
            'external_id' => 'chg_1',
            'agreement_id' => 'agr_1',
            'amount_cents' => 15000,
            'currency' => 'dkk',
            'status' => Payment::PENDING,
            'idempotency_key' => Payment::recurringKey('agr_1', now()->addDays(3)),
        ]);

        return [$enrollment, $payment];
    }
}
