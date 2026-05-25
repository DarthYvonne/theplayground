<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public DirectMessage $message,
        public User $sender,
        public User $recipient,
        public ?Course $course,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->course
            ? '[' . $this->course->title . '] Ny besked fra ' . $this->sender->name
            : 'Ny besked fra ' . $this->sender->name;

        return new Envelope(
            subject: $subject,
            replyTo: [new Address($this->sender->email, $this->sender->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-message',
            with: [
                'bodyText' => $this->message->body,
                'sender' => $this->sender,
                'recipient' => $this->recipient,
                'course' => $this->course,
                'threadUrl' => route('beskeder.show', $this->sender),
                'settingsUrl' => route('beskeder.index'),
            ],
        );
    }
}
