<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourseBroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Course $course,
        public User $sender,
        public string $subjectLine,
        public string $bodyText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . $this->course->title . '] ' . $this->subjectLine,
            replyTo: [$this->sender->email => $this->sender->name],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.broadcast',
            with: ['bodyText' => $this->bodyText, 'course' => $this->course, 'sender' => $this->sender],
        );
    }
}
