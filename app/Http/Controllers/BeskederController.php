<?php

namespace App\Http\Controllers;

use App\Jobs\SendNewMessageMail;
use App\Models\Course;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Http\Request;

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
            'recipient_users' => ['nullable', 'array'],
            'recipient_users.*' => ['integer'],
            'recipient_courses' => ['nullable', 'array'],
            'recipient_courses.*' => ['integer'],
            'body' => ['required', 'string', 'max:8000'],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            return back()->withErrors(['body' => 'Skriv en besked.'])->withInput();
        }

        $userIds = collect($data['recipient_users'] ?? [])->map(fn ($i) => (int) $i)->filter()->unique()->values();
        $courseIds = collect($data['recipient_courses'] ?? [])->map(fn ($i) => (int) $i)->filter()->unique()->values();

        if ($userIds->isEmpty() && $courseIds->isEmpty()) {
            return back()->withErrors(['recipients' => 'Vælg mindst én modtager.'])->withInput();
        }

        // Build [user_id => ['user' => User, 'course' => Course|null]].
        // Course fan-out wins over a direct user pick so the message is tagged with the course.
        $targets = [];

        foreach ($courseIds as $cid) {
            $course = Course::find($cid);
            if (!$course || !$this->canBroadcastTo($me, $course)) continue;
            $members = $course->activeEnrollments()->with('user')->get()->pluck('user')->filter();
            foreach ($course->trainers as $trainer) {
                if ($trainer->id !== $me->id) {
                    $members->push($trainer);
                }
            }
            foreach ($members as $u) {
                if ($u->id === $me->id) continue;
                if (!isset($targets[$u->id])) {
                    $targets[$u->id] = ['user' => $u, 'course' => $course];
                }
            }
        }

        foreach ($userIds as $uid) {
            if ($uid === $me->id) continue;
            if (isset($targets[$uid])) continue;
            $u = User::find($uid);
            if (!$u) continue;
            $targets[$uid] = ['user' => $u, 'course' => null];
        }

        $sent = 0;
        foreach ($targets as $t) {
            $msg = DirectMessage::create([
                'sender_id' => $me->id,
                'recipient_id' => $t['user']->id,
                'via_course_id' => $t['course']?->id,
                'body' => $body,
            ]);
            $this->afterSend($msg, $me, $t['user'], $t['course']);
            $sent++;
        }

        if ($sent === 0) {
            return back()->withErrors(['recipients' => 'Ingen gyldige modtagere.'])->withInput();
        }

        // Single direct-user pick → drop straight into the thread (no flash; the message itself is visible).
        if ($sent === 1 && $courseIds->isEmpty() && $userIds->count() === 1) {
            $only = User::find($userIds->first());
            return redirect()->route('beskeder.show', $only);
        }

        $word = $sent === 1 ? 'modtager' : 'modtagere';
        return redirect()->route('beskeder.index')->with('status', "Beskeden er sendt til {$sent} {$word}.");
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
        $type = $request->query('type'); // null = mixed (legacy autocomplete), 'user', or 'course'

        $users = [];
        if ($type !== 'course') {
            $userLimit = $type === 'user' ? 200 : 8;
            $users = User::where('id', '!=', $me->id)
                ->when($q !== '', fn ($qq) => $qq->where('name', 'like', "%{$q}%"))
                ->orderBy('name')
                ->limit($userLimit)
                ->get(['id', 'name', 'picture_path', 'role'])
                ->map(fn (User $u) => [
                    'type' => 'user',
                    'id' => $u->id,
                    'label' => $u->name,
                    'sub' => $this->roleLabel($u->role),
                    'picture_url' => $u->pictureUrl(),
                    'initials' => $u->initials(),
                ])->all();
        }

        $courses = [];
        if ($type !== 'user') {
            $courseLimit = $type === 'course' ? 200 : 6;
            $courses = $this->broadcastableCourses($me)
                ->when($q !== '', fn ($c) => $c->filter(fn ($x) => stripos($x->title, $q) !== false))
                ->take($courseLimit)
                ->map(fn (Course $c) => [
                    'type' => 'course',
                    'id' => $c->id,
                    'label' => $c->title,
                    'sub' => 'Hold · ' . $c->activeCount() . ' deltagere',
                    'picture_url' => $c->imageUrl(),
                    'initials' => mb_strtoupper(mb_substr($c->title, 0, 2)),
                ])->values()->all();
        }

        return response()->json([
            'results' => array_merge($courses, $users),
        ]);
    }

    private function afterSend(DirectMessage $msg, User $sender, User $recipient, ?Course $course): void
    {
        if (!$recipient->email_on_message || !$recipient->email) {
            return;
        }

        try {
            SendNewMessageMail::dispatch($msg)->delay(now()->addSeconds(30));
        } catch (\Throwable $e) {
            logger()->error('SendNewMessageMail dispatch failed', [
                'message_id' => $msg->id,
                'to' => $recipient->id,
                'err' => $e->getMessage(),
            ]);
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
        if ($u->isTrainer() && $course->hasTrainer($u)) return true;
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
