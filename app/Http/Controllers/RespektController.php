<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\Respekt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RespektController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:platform_message,course_message,enrollment,comment'],
            'target_id' => ['required', 'integer'],
        ]);

        $existing = Respekt::where('user_id', $request->user()->id)
            ->where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])
            ->first();

        if ($existing) {
            $existing->delete();
            $respekted = false;
        } else {
            Respekt::create([
                'user_id' => $request->user()->id,
                'target_type' => $data['target_type'],
                'target_id' => $data['target_id'],
            ]);
            $respekted = true;
            $this->notifyAuthor($request->user(), $data['target_type'], (int) $data['target_id']);
        }

        $count = Respekt::where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])
            ->count();

        return response()->json([
            'respekted' => $respekted,
            'count' => $count,
        ]);
    }

    private function notifyAuthor(User $actor, string $type, int $targetId): void
    {
        if ($type === 'enrollment') {
            $enrollment = Enrollment::with('course')->find($targetId);
            if (!$enrollment) return;
            $authorId = $enrollment->user_id;
            $link = $enrollment->course ? route('courses.show', $enrollment->course) : route('dashboard');
            $courseId = $enrollment->course_id;
        } elseif ($type === 'comment') {
            $comment = \App\Models\FeedComment::with('message')->find($targetId);
            if (!$comment) return;
            $authorId = $comment->user_id;
            $message = $comment->message;
            $courseId = $message?->course_id;
            $link = $message && $message->channel_type === 'course' && $courseId
                ? route('chat.course', $courseId)
                : route('dashboard') . ($message ? ('#pm-' . $message->id) : '');
        } else {
            $message = Message::find($targetId);
            if (!$message) return;
            $authorId = $message->user_id;
            $courseId = $message->course_id;
            $link = $type === 'course_message' && $courseId
                ? route('chat.course', $courseId)
                : route('dashboard') . '#pm-' . $message->id;
        }

        if ($authorId === $actor->id) return;

        AppNotification::create([
            'user_id' => $authorId,
            'type' => 'respekt',
            'title' => $actor->name . ' gav dig respekt',
            'link' => $link,
            'course_id' => $courseId,
            'actor_id' => $actor->id,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:platform_message,course_message,enrollment,comment'],
            'target_id' => ['required', 'integer'],
        ]);

        $userIds = Respekt::where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])
            ->orderByDesc('created_at')
            ->pluck('user_id');

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $payload = $userIds->map(function ($id) use ($users) {
            $u = $users[$id] ?? null;
            if (!$u) return null;
            return [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => $u->initials(),
                'picture_url' => $u->pictureUrl(),
                'profile_url' => route('members.show', $u),
            ];
        })->filter()->values();

        return response()->json(['users' => $payload]);
    }
}
