<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Run summary for the daily MobilePay recurring charge job, sent to the platform
 * owner. Plain billing/admin email (outside the Beskeder in-app messaging rule).
 */
class RecurringRunSummaryMail extends Mailable
{
    /** @param array{charged:int,total_cents:int,currency:string,failed:int,expired:int,failures:array<int,string>,ran_at:string} $summary */
    public function __construct(public array $summary) {}

    public function envelope(): Envelope
    {
        $s = $this->summary;
        $subject = sprintf(
            'Abonnementer: %d opkrævet · %d fejlede (%s)',
            $s['charged'], $s['failed'], $s['ran_at'],
        );

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recurring-run-summary',
            with: ['s' => $this->summary],
        );
    }
}
