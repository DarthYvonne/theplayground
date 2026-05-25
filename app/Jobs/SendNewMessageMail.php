<?php

namespace App\Jobs;

use App\Mail\NewMessageMail;
use App\Models\DirectMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewMessageMail implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public DirectMessage $message) {}

    public function handle(): void
    {
        if ($this->message->read_at) {
            return;
        }

        $recipient = $this->message->recipient;
        if (!$recipient || !$recipient->email_on_message || !$recipient->email) {
            return;
        }

        Mail::to($recipient->email)->send(new NewMessageMail(
            $this->message,
            $this->message->sender,
            $recipient,
            $this->message->viaCourse,
        ));
    }
}
