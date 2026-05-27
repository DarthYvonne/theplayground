<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\FeedComment;
use App\Models\Message;
use App\Models\Respekt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    public function index(Request $request, Message $message): JsonResponse
    {
        $this->authorizeView($request, $message);

        $rows = FeedComment::with('user')
            ->where('message_id', $message->id)
            ->orderBy('id')
            ->get();

        $viewerId = $request->user()->id;

        // Respekt counts/mine for these comments
        $ids = $rows->pluck('id')->all();
        $counts = [];
        $mine = [];
        if (!empty($ids)) {
            foreach (
                Respekt::selectRaw('target_id, COUNT(*) as c')
                    ->where('target_type', 'comment')
                    ->whereIn('target_id', $ids)
                    ->groupBy('target_id')
                    ->get()
                as $r
            ) {
                $counts[(int) $r->target_id] = (int) $r->c;
            }
            foreach (
                Respekt::where('user_id', $viewerId)
                    ->where('target_type', 'comment')
                    ->whereIn('target_id', $ids)
                    ->pluck('target_id')
                as $id
            ) {
                $mine[(int) $id] = true;
            }
        }

        $payload = $rows->map(function (FeedComment $c) use ($viewerId, $counts, $mine) {
            return [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'body' => $c->body,
                'mine' => $c->user_id === $viewerId,
                'time_human' => $c->created_at->diffForHumans(),
                'user' => [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                    'initials' => $c->user->initials(),
                    'picture_url' => $c->user->pictureUrl(),
                    'profile_url' => route('members.show', $c->user),
                ],
                'respekt_count' => $counts[$c->id] ?? 0,
                'you_respekted' => isset($mine[$c->id]),
            ];
        })->values();

        return response()->json(['comments' => $payload]);
    }

    public function store(Request $request, Message $message): JsonResponse
    {
        $this->authorizeView($request, $message);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parentId = null;
        if (!empty($data['parent_id'])) {
            $parent = FeedComment::where('message_id', $message->id)->find($data['parent_id']);
            if ($parent) {
                // Flatten one level: replies to replies attach to the original top-level comment.
                $parentId = $parent->parent_id ?? $parent->id;
            }
        }

        $comment = FeedComment::create([
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'body' => trim($data['body']),
        ]);

        $this->notifyOnComment($request, $message, $comment);

        return response()->json([
            'id' => $comment->id,
            'comments_count' => FeedComment::where('message_id', $message->id)->count(),
        ]);
    }

    public function update(Request $request, FeedComment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            abort(403);
        }
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $comment->update(['body' => trim($data['body'])]);
        return response()->json(['id' => $comment->id, 'body' => $comment->body]);
    }

    public function destroy(Request $request, FeedComment $comment): JsonResponse
    {
        $viewer = $request->user();
        $message = $comment->message;
        $isAuthor = $comment->user_id === $viewer->id;
        $isPostAuthor = $message && $message->user_id === $viewer->id;
        if (!$isAuthor && !$isPostAuthor && !$viewer->isOwner()) {
            abort(403);
        }
        $messageId = $comment->message_id;
        DB::transaction(function () use ($comment) {
            // Wipe respekts for this comment + its replies, then delete cascade via FK
            $replyIds = FeedComment::where('parent_id', $comment->id)->pluck('id')->all();
            $allIds = array_merge([$comment->id], $replyIds);
            Respekt::where('target_type', 'comment')->whereIn('target_id', $allIds)->delete();
            $comment->delete();
        });
        return response()->json([
            'ok' => true,
            'comments_count' => FeedComment::where('message_id', $messageId)->count(),
        ]);
    }

    private function authorizeView(Request $request, Message $message): void
    {
        $user = $request->user();
        if ($message->channel_type === 'platform') return;
        if ($message->channel_type === 'course') {
            if ($user->isOwner()) return;
            $courseId = $message->course_id;
            $hasEnrollment = $user->activeEnrollments()->where('course_id', $courseId)->exists();
            if ($hasEnrollment) return;
            if ($user->isTrainer() && $user->trainerCourses()->where('courses.id', $courseId)->exists()) return;
            abort(403);
        }
        abort(404);
    }

    private function notifyOnComment(Request $request, Message $message, FeedComment $comment): void
    {
        $actor = $request->user();
        $link = $message->channel_type === 'course' && $message->course_id
            ? route('chat.course', $message->course_id)
            : route('feed') . '#pm-' . $message->id;

        // Notify the post author (unless commenting on own post)
        if ($message->user_id !== $actor->id) {
            AppNotification::create([
                'user_id' => $message->user_id,
                'type' => 'comment',
                'title' => $actor->name . ' kommenterede på dit opslag',
                'link' => $link,
                'course_id' => $message->course_id,
                'actor_id' => $actor->id,
            ]);
        }

        // Notify the parent-comment author (if reply, and not self, not post-author duplicate)
        if ($comment->parent_id) {
            $parent = FeedComment::find($comment->parent_id);
            if ($parent && $parent->user_id !== $actor->id && $parent->user_id !== $message->user_id) {
                AppNotification::create([
                    'user_id' => $parent->user_id,
                    'type' => 'comment',
                    'title' => $actor->name . ' svarede på din kommentar',
                    'link' => $link,
                    'course_id' => $message->course_id,
                    'actor_id' => $actor->id,
                ]);
            }
        }
    }
}
