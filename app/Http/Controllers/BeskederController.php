<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageMail;
use App\Models\Course;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BeskederController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();

        $msgs = DirectMessage::where('sender_id', $me->id)
            ->orWhere('recipient_id', $me->id)
            ->with(['sender', 'recipient', 'viaCourse'])
            ->orderByDesc('created_at')
            ->get();

        $threads = [];
        foreach ($msgs as $m) {
            $otherId = $m->sender_id === $me->id ? $m->recipient_id : $m->sender_id;
            if (isset($threads[$otherId])) continue;
            $other = $m->sender_id === $me->id ? $m->recipient : $m->sender;
            if (!$other) continue;
            $threads[$otherId] = [
                'user' => $other,
                'last' => $m,
                'last_mine' => $m->sender_id === $me->id,
                'unread' => 0,
            ];
        }

        if ($threads) {
            $counts = DirectMessage::where('recipient_id', $me->id)
                ->whereIn('sender_id', array_keys($threads))
                ->whereNull('read_at')
                ->selectRaw('sender_id, COUNT(*) as c')
                ->groupBy('sender_id')
                ->pluck('c', 'sender_id');
            foreach ($threads as $id => &$t) {
                $t['unread'] = (int) ($counts[$id] ?? 0);
            }
            unset($t);
        }

        $prefill = null;
        if ($request->filled('til')) {
            $prefill = User::find((int) $request->query('til'));
        }

        $prefillCourse = null;
        if ($request->filled('hold')) {
            $course = Course::find((int) $request->query('hold'));
            if ($course && $this->canBroadcastTo($me, $course)) {
                $prefillCourse = $course;
            }
        }

        return view('beskeder.index', [
            'threads' => array_values($threads),
            'prefill' => $prefill,
            'prefillCourse' => $prefillCourse,
            'canBroadcast' => $this->broadcastableCourses($me)->isNotEmpty(),
        ]);
    }

    public function show(Request $request, User $user)
    {
        $me = $request->user();
        if ($user->id === $me->id) {
            return redirect()->route('beskeder.index');
        }

        DirectMessage::where('recipient_id', $me->id)
            ->where('sender_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = DirectMessage::where(function ($q) use ($me, $user) {
            $q->where(function ($q) use ($me, $user) {
                $q->where('sender_id', $me->id)->where('recipient_id', $user->id);
            })->orWhere(function ($q) use ($me, $user) {
                $q->where('sender_id', $user->id)->where('recipient_id', $me->id);
            });
        })->with(['sender', 'viaCourse'])->orderBy('created_at')->get();

        return view('beskeder.show', [
            'other' => $user,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'recipient_type' => ['required', 'in:user,course'],
            'recipient_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:8000'],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            return back()->withErrors(['body' => 'Skriv en besked.'])->withInput();
        }

        if ($data['recipient_type'] === 'course') {
            $course = Course::findOrFail($data['recipient_id']);
            abort_unless($this->canBroadcastTo($me, $course), 403);

            $recipients = $course->activeEnrollments()->with('user')->get()->pluck('user')->filter();
            if ($course->trainer_id && $course->trainer_id !== $me->id) {
                $recipients->push($course->trainer);
            }
            $recipients = $recipients->unique('id')->reject(fn ($u) => $u->id === $me->id)->values();

            $sent = 0;
            foreach ($recipients as $u) {
                $msg = DirectMessage::create([
                    'sender_id' => $me->id,
                    'recipient_id' => $u->id,
                    'via_course_id' => $course->id,
                    'body' => $body,
                ]);
                $this->afterSend($msg, $me, $u, $course);
                $sent++;
            }

            $word = $sent === 1 ? 'modtager' : 'modtagere';
            return redirect()->route('beskeder.index')
                ->with('status', "Beskeden er sendt til {$sent} {$word} på {$course->title}.");
        }

        $recipient = User::findOrFail($data['recipient_id']);
        if ($recipient->id === $me->id) {
            return back()->withErrors(['recipient_id' => 'Du kan ikke sende til dig selv.'])->withInput();
        }

        $msg = DirectMessage::create([
            'sender_id' => $me->id,
            'recipient_id' => $recipient->id,
            'body' => $body,
        ]);
        $this->afterSend($msg, $me, $recipient, null);

        return redirect()->route('beskeder.show', $recipient)->with('status', 'Besked sendt.');
    }

    public function updateSettings(Request $request)
    {
        $request->validate(['email_on_message' => ['nullable']]);
        $me = $request->user();
        $me->email_on_message = $request->boolean('email_on_message');
        $me->save();
        return back()->with('status', $me->email_on_message ? 'Mail-notifikationer slået til.' : 'Mail-notifikationer slået fra.');
    }

    public function recipients(Request $request)
    {
        $me = $request->user();
        $q = trim((string) $request->query('q', ''));

        $users = User::where('id', '!=', $me->id)
            ->when($q !== '', fn ($qq) => $qq->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'picture_path', 'role'])
            ->map(fn (User $u) => [
                'type' => 'user',
                'id' => $u->id,
                'label' => $u->name,
                'sub' => $this->roleLabel($u->role),
                'picture_url' => $u->pictureUrl(),
                'initials' => $u->initials(),
            ])->all();

        $courses = $this->broadcastableCourses($me)
            ->when($q !== '', fn ($c) => $c->filter(fn ($x) => stripos($x->title, $q) !== false))
            ->take(6)
            ->map(fn (Course $c) => [
                'type' => 'course',
                'id' => $c->id,
                'label' => $c->title,
                'sub' => 'Hold · ' . $c->activeCount() . ' deltagere',
                'picture_url' => $c->imageUrl(),
                'initials' => mb_strtoupper(mb_substr($c->title, 0, 2)),
            ])->values()->all();

        return response()->json([
            'results' => array_merge($courses, $users),
        ]);
    }

    private function afterSend(DirectMessage $msg, User $sender, User $recipient, ?Course $course): void
    {
        if ($recipient->email_on_message && $recipient->email) {
            try {
                Mail::to($recipient->email)->queue(new NewMessageMail($msg, $sender, $recipient, $course));
            } catch (\Throwable $e) {
                logger()->warning('NewMessageMail dispatch failed', ['err' => $e->getMessage(), 'to' => $recipient->id]);
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\Course> */
    private function broadcastableCourses(User $u)
    {
        if ($u->isOwner()) {
            return Course::where('is_active', true)->orderBy('title')->get();
        }
        $ids = collect();
        if ($u->isTrainer()) {
            $ids = $ids->merge($u->trainerCourses()->pluck('id'));
        }
        if ($u->isTrainer() || $u->isAssistant()) {
            $ids = $ids->merge($u->activeEnrollments()->pluck('course_id'));
        }
        $ids = $ids->unique()->values();
        if ($ids->isEmpty()) return collect();
        return Course::whereIn('id', $ids)->orderBy('title')->get();
    }

    private function canBroadcastTo(User $u, Course $course): bool
    {
        if ($u->isOwner()) return true;
        if ($u->isTrainer() && $course->trainer_id === $u->id) return true;
        if (($u->isTrainer() || $u->isAssistant()) && $u->enrolledIn($course)) return true;
        return false;
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'trainer' => 'Træner',
            'assistant' => 'Assistent',
            default => 'Medlem',
        };
    }
}
