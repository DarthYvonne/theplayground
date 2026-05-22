<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Mail\CourseBroadcastMail;
use App\Models\AppNotification;
use App\Models\Course;
use App\Models\EmailBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BroadcastController extends Controller
{
    public function create(Request $request, Course $course) {
        $this->authorize($request, $course);
        $history = $course->emailBroadcasts()->with('sender')->latest('sent_at')->limit(20)->get();
        return view('trainer.broadcast', compact('course','history'));
    }

    public function send(Request $request, Course $course) {
        $this->authorize($request, $course);
        $data = $request->validate([
            'subject' => ['required','string','max:200'],
            'body' => ['required','string','max:8000'],
        ]);

        $recipients = $course->activeEnrollments()->with('user')->get()->pluck('user')->filter();

        foreach ($recipients as $u) {
            try {
                Mail::to($u->email)->send(new CourseBroadcastMail($course, $request->user(), $data['subject'], $data['body']));
            } catch (\Throwable $e) {
                // Fail-soft: keep going for remaining recipients
                logger()->warning('Broadcast mail failed', ['email' => $u->email, 'err' => $e->getMessage()]);
            }
            AppNotification::create([
                'user_id' => $u->id,
                'type' => 'broadcast',
                'title' => 'E-mail fra ' . $request->user()->name . ': ' . $data['subject'],
                'body' => mb_substr($data['body'], 0, 200),
                'link' => route('courses.show', $course),
                'course_id' => $course->id,
                'actor_id' => $request->user()->id,
            ]);
        }

        EmailBroadcast::create([
            'course_id' => $course->id,
            'sender_id' => $request->user()->id,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'recipient_count' => $recipients->count(),
            'sent_at' => now(),
        ]);

        $n = $recipients->count();
        return back()->with('status', 'Sendt til ' . $n . ' ' . ($n === 1 ? 'modtager' : 'modtagere') . '.');
    }

    private function authorize(Request $request, Course $course): void {
        $u = $request->user();
        abort_unless($u->isOwner() || $course->trainer_id === $u->id, 403);
    }
}
