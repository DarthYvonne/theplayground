<?php

namespace App\Console\Commands;

use App\Mail\RecurringRunSummaryMail;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Gateway;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Creates the upcoming charge for every active MobilePay membership whose
 * renewal falls within the lead window. MobilePay requires us to create each
 * periodic charge ahead of the due date (it then pulls the money and retries on
 * failure). Idempotent via the payments ledger; emails the owner a run summary.
 */
class ChargeDueMemberships extends Command
{
    protected $signature = 'payments:charge-due {--dry-run : List what would be charged without calling MobilePay}';

    protected $description = 'Create due MobilePay recurring charges and email the owner a run summary';

    public function handle(Gateway $gateway): int
    {
        $provider = $gateway->recurring();
        if (! $provider) {
            $this->warn('No recurring provider configured — nothing to do.');

            return self::SUCCESS;
        }

        $leadDays = (int) config('payments.recurring.lead_days', 2);
        $currency = (string) config('payments.currency', 'dkk');
        $cutoff = Carbon::now()->addDays($leadDays)->endOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $due = Enrollment::with('course', 'user')
            ->where('status', 'active')
            ->where('provider', 'mobilepay')
            ->whereNotNull('mobilepay_agreement_id')
            ->where('cancel_at_period_end', false)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', $cutoff)
            ->get();

        $charged = 0;
        $totalCents = 0;
        $failures = [];

        foreach ($due as $enrollment) {
            $course = $enrollment->course;
            if (! $course || $course->price_cents <= 0) {
                continue;
            }

            $periodStart = $enrollment->current_period_end->copy();
            // MobilePay needs the due date at least one day ahead.
            $dueDate = $periodStart->isAfter(Carbon::now()->addDay())
                ? $periodStart
                : Carbon::now()->addDay();

            $key = Payment::recurringKey($enrollment->mobilepay_agreement_id, $periodStart);
            if (Payment::where('idempotency_key', $key)->exists()) {
                continue; // already charged this period
            }

            if ($dryRun) {
                $this->line(sprintf('would charge #%d "%s" %d øre due %s',
                    $enrollment->id, $course->title, $course->price_cents, $dueDate->toDateString()));

                continue;
            }

            // Reserve the ledger row first so a crash mid-call can't double-charge.
            $payment = Payment::create([
                'idempotency_key' => $key,
                'user_id' => $enrollment->user_id,
                'enrollment_id' => $enrollment->id,
                'provider' => 'mobilepay',
                'kind' => Payment::KIND_RECURRING,
                'agreement_id' => $enrollment->mobilepay_agreement_id,
                'amount_cents' => $course->price_cents,
                'currency' => $currency,
                'status' => Payment::PENDING,
                'period_start' => $periodStart,
                'period_end' => $periodStart->copy()->addMonth(),
                'due_at' => $dueDate,
            ]);

            try {
                $result = $provider->chargeMembership(
                    $enrollment->mobilepay_agreement_id,
                    $course->price_cents,
                    $dueDate,
                    $course->title,
                    $currency,
                );
                $payment->update(['external_id' => $result->reference, 'status' => Payment::RESERVED]);
                $charged++;
                $totalCents += $course->price_cents;
            } catch (\Throwable $e) {
                $payment->update(['status' => Payment::FAILED]);
                $failures[] = sprintf('#%d %s — %s', $enrollment->id, $course->title, $e->getMessage());
            }
        }

        $summary = [
            'charged' => $charged,
            'total_cents' => $totalCents,
            'currency' => $currency,
            'failed' => count($failures),
            'expired' => 0,
            'failures' => $failures,
            'ran_at' => Carbon::now()->format('d.m.Y H:i'),
        ];

        $this->info(sprintf('Charged %d (%s %s), %d failed.',
            $charged, number_format($totalCents / 100, 2), strtoupper($currency), count($failures)));

        if (! $dryRun) {
            $this->mailOwner($summary);
        }

        return self::SUCCESS;
    }

    private function mailOwner(array $summary): void
    {
        $email = config('payments.owner_email') ?: User::where('role', 'owner')->value('email');
        if (! $email) {
            $this->warn('No owner email configured — skipping run-summary mail.');

            return;
        }
        try {
            Mail::to($email)->queue(new RecurringRunSummaryMail($summary));
        } catch (\Throwable $e) {
            $this->warn('Run-summary mail failed: '.$e->getMessage());
        }
    }
}
