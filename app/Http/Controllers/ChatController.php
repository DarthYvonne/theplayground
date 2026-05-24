<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\MessageRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function platform(Request $request)
    {
        return view('chat.platform');
    }

    public function course(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        return view('chat.course', compact('course'));
    }

    public function listPlatform(Request $request): JsonResponse
    {
        $messages = Message::with('user')->where('channel_type','platform')->orderByDesc('id')->limit(100)->get()->reverse()->values();
        $request->user()->forceFill(['last_seen_platform_chat_at' => now()])->save();
        return response()->json(['messages' => $this->serialize($messages, $request->user()->id)]);
    }

    public function listCourse(Request $request, Course $course): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        $messages = Message::with('user')->where('channel_type','course')->where('course_id', $course->id)->orderByDesc('id')->limit(200)->get()->reverse()->values();
        MessageRead::updateOrCreate(
            ['user_id' => $request->user()->id, 'course_id' => $course->id],
            ['last_read_at' => now()]
        );
        return response()->json(['messages' => $this->serialize($messages, $request->user()->id)]);
    }

    public function sendPlatform(Request $request): JsonResponse
    {
        $data = $request->validate(['body' => ['required','string','max:2000']]);
        $m = Message::create([
            'channel_type' => 'platform',
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        return response()->json(['message' => $this->serializeOne($m->load('user'), $request->user()->id)]);
    }

    public function sendCourse(Request $request, Course $course): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        $data = $request->validate(['body' => ['required','string','max:2000']]);
        $sender = $request->user();
        $m = Message::create([
            'channel_type' => 'course',
            'course_id' => $course->id,
            'user_id' => $sender->id,
            'body' => $data['body'],
        ]);
        $this->notifyHoldMembers($course, $sender, $m);
        return response()->json(['message' => $this->serializeOne($m->load('user'), $sender->id)]);
    }

    private function notifyHoldMembers(Course $course, $sender, Message $message): void
    {
        $recipientIds = Enrollment::where('course_id', $course->id)
            ->where('status', 'active')
            ->pluck('user_id')
            ->push($course->trainer_id)
            ->unique()
            ->reject(fn ($id) => $id === $sender->id)
            ->values();

        if ($recipientIds->isEmpty()) return;

        $title = $sender->name . ' skrev i ' . $course->title;
        $body = mb_substr($message->body, 0, 200);
        $link = route('chat.course', $course);
        $now = now();

        $rows = $recipientIds->map(fn ($uid) => [
            'user_id' => $uid,
            'type' => 'hold_message',
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'course_id' => $course->id,
            'actor_id' => $sender->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        AppNotification::insert($rows);
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        $u = $request->user();
        $ok = $u->isOwner() || $course->trainer_id === $u->id || $u->enrolledIn($course);
        abort_unless($ok, 403);
    }

    private function serialize($messages, int $viewerId): array
    {
        return $messages->map(fn ($m) => $this->serializeOne($m, $viewerId))->all();
    }

    private function serializeOne(Message $m, int $viewerId): array
    {
        return [
            'id' => $m->id,
            'body' => $m->body,
            'created_at' => $m->created_at->toIso8601String(),
            'time_human' => $m->created_at->diffForHumans(),
            'mine' => $m->user_id === $viewerId,
            'user' => [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'initials' => $m->user->initials(),
                'picture_url' => $m->user->pictureUrl(),
                'role' => $m->user->role,
            ],
        ];
    }
}
