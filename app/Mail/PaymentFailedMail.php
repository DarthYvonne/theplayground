<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use SerializesModels;

    public function __construct(public User $user, public Course $course) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Din betaling fejlede — opdater dit kort');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-failed',
            with: [
                'user' => $this->user,
                'course' => $this->course,
                'billingUrl' => route('profile.billing'),
            ],
        );
    }
}
