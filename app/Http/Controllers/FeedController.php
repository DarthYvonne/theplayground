<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeedComment;
use App\Models\Message;
use App\Models\MessageView;
use App\Models\Respekt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    private const LIMIT = 50;

    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        $courseIds = $this->accessibleCourseIds($user);

        $items = collect();

        // Platform messages — visible to all authenticated users
        Message::with(['user', 'mediaItem'])
            ->where('channel_type', 'platform')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->each(function ($m) use ($items, $user) {
                $items->push([
                    'id' => 'pm-' . $m->id,
                    'type' => 'platform_message',
                    'sort_key' => $m->created_at->getTimestamp() . '.' . $m->id,
                    'created_at' => $m->created_at->toIso8601String(),
                    'time_human' => $m->created_at->diffForHumans(),
                    'body' => $m->body,
                    'image_url' => $m->imageUrl(),
                    'video_url' => $m->videoUrl(),
                    'video_processing_status' => $m->video_processing_status,
                    'media_item' => $m->mediaItem?->toPayload(),
                    'mine' => $m->user_id === $user->id,
                    'user' => $this->serializeUser($m->user),
                    'course' => null,
                ]);
            });

        if ($courseIds->isNotEmpty()) {
            // Course chat messages from courses the user can see
            Message::with(['user', 'course'])
                ->where('channel_type', 'course')
                ->whereIn('course_id', $courseIds)
                ->orderByDesc('id')
                ->limit(self::LIMIT)
                ->get()
                ->each(function ($m) use ($items, $user) {
                    $items->push([
                        'id' => 'cm-' . $m->id,
                        'type' => 'course_message',
                        'sort_key' => $m->created_at->getTimestamp() . '.' . $m->id,
                        'created_at' => $m->created_at->toIso8601String(),
                        'time_human' => $m->created_at->diffForHumans(),
                        'body' => $m->body,
                        'mine' => $m->user_id === $user->id,
                        'user' => $this->serializeUser($m->user),
                        'course' => $this->serializeCourse($m->course),
                    ]);
                });

            // New enrollment events on those courses
            Enrollment::with(['user', 'course'])
                ->whereIn('course_id', $courseIds)
                ->where('status', 'active')
                ->whereNotNull('enrolled_at')
                ->orderByDesc('enrolled_at')
                ->limit(self::LIMIT)
                ->get()
                ->each(function ($e) use ($items, $user) {
                    if (!$e->user || !$e->course) return;
                    $ts = ($e->enrolled_at ?? $e->created_at);
                    $items->push([
                        'id' => 'en-' . $e->id,
                        'type' => 'enrollment',
                        'sort_key' => $ts->getTimestamp() . '.' . $e->id,
                        'created_at' => $ts->toIso8601String(),
                        'time_human' => $ts->diffForHumans(),
                        'body' => null,
                        'mine' => $e->user_id === $user->id,
                        'user' => $this->serializeUser($e->user),
                        'course' => $this->serializeCourse($e->course),
                    ]);
                });
        }

        $merged = $items
            ->sortByDesc('sort_key')
            ->take(self::LIMIT)
            ->values();

        $merged = $this->attachRespekt($merged, $user->id);
        $merged = $this->attachCommentCounts($merged);
        $merged = $this->attachViewCounts($merged);

        $payload = $merged->map(fn ($i) => collect($i)->except('sort_key')->all())->values();

        return response()->json(['items' => $payload]);
    }

    public function recordView(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        // Don't count author's own view
        if ($message->user_id === $user->id) {
            return response()->json(['ok' => true, 'counted' => false]);
        }

        // Gate by feed visibility: platform messages are public to auth users;
        // course messages require access to the course.
        if ($message->channel_type === 'course') {
            $courseIds = $this->accessibleCourseIds($user);
            if (!$courseIds->contains($message->course_id)) {
                abort(403);
            }
        } elseif ($message->channel_type !== 'platform') {
            abort(404);
        }

        $view = MessageView::firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'ok' => true,
            'counted' => $view->wasRecentlyCreated,
        ]);
    }

    private function attachRespekt(\Illuminate\Support\Collection $items, int $viewerId): \Illuminate\Support\Collection
    {
        if ($items->isEmpty()) return $items;

        $keysByType = [];
        foreach ($items as $it) {
            $type = $it['type'];
            $rawId = (int) explode('-', $it['id'], 2)[1];
            $keysByType[$type][] = $rawId;
        }

        $counts = [];
        $mine = [];
        foreach ($keysByType as $type => $ids) {
            $ids = array_values(array_unique($ids));
            foreach (
                Respekt::selectRaw('target_id, COUNT(*) as c')
                    ->where('target_type', $type)
                    ->whereIn('target_id', $ids)
                    ->groupBy('target_id')
                    ->get()
                as $row
            ) {
                $counts[$type][(int) $row->target_id] = (int) $row->c;
            }
            foreach (
                Respekt::where('user_id', $viewerId)
                    ->where('target_type', $type)
                    ->whereIn('target_id', $ids)
                    ->pluck('target_id')
                as $id
            ) {
                $mine[$type][(int) $id] = true;
            }
        }

        return $items->map(function ($it) use ($counts, $mine) {
            $type = $it['type'];
            $rawId = (int) explode('-', $it['id'], 2)[1];
            $it['respekt_count'] = $counts[$type][$rawId] ?? 0;
            $it['you_respekted'] = isset($mine[$type][$rawId]);
            $it['target_type'] = $type;
            $it['target_id'] = $rawId;
            return $it;
        });
    }

    private function attachCommentCounts(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        $messageIds = $items
            ->filter(fn ($it) => in_array($it['type'], ['platform_message', 'course_message'], true))
            ->map(fn ($it) => (int) $it['target_id'])
            ->unique()
            ->values()
            ->all();

        $counts = [];
        if (!empty($messageIds)) {
            foreach (
                FeedComment::selectRaw('message_id, COUNT(*) as c')
                    ->whereIn('message_id', $messageIds)
                    ->groupBy('message_id')
                    ->get()
                as $row
            ) {
                $counts[(int) $row->message_id] = (int) $row->c;
            }
        }

        return $items->map(function ($it) use ($counts) {
            $isMsg = in_array($it['type'], ['platform_message', 'course_message'], true);
            $it['can_comment'] = $isMsg;
            $it['comments_count'] = $isMsg ? ($counts[(int) $it['target_id']] ?? 0) : 0;
            return $it;
        });
    }

    private function attachViewCounts(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        $messageIds = $items
            ->filter(fn ($it) => in_array($it['type'], ['platform_message', 'course_message'], true))
            ->map(fn ($it) => (int) $it['target_id'])
            ->unique()
            ->values()
            ->all();

        $counts = [];
        if (!empty($messageIds)) {
            foreach (
                MessageView::selectRaw('message_id, COUNT(*) as c')
                    ->whereIn('message_id', $messageIds)
                    ->groupBy('message_id')
                    ->get()
                as $row
            ) {
                $counts[(int) $row->message_id] = (int) $row->c;
            }
        }

        return $items->map(function ($it) use ($counts) {
            $isMsg = in_array($it['type'], ['platform_message', 'course_message'], true);
            $it['views_count'] = $isMsg ? ($counts[(int) $it['target_id']] ?? 0) : 0;
            return $it;
        });
    }

    private function accessibleCourseIds($user)
    {
        if ($user->isOwner()) {
            return Course::pluck('id');
        }
        $ids = $user->activeEnrollments()->pluck('course_id');
        if ($user->isTrainer()) {
            $ids = $ids->merge($user->trainerCourses()->pluck('id'));
        }
        return $ids->unique()->values();
    }

    private function serializeUser($u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'initials' => $u->initials(),
            'picture_url' => $u->pictureUrl(),
            'profile_url' => route('members.show', $u),
            'role' => $u->role,
        ];
    }

    private function serializeCourse(?Course $c): ?array
    {
        if (!$c) return null;
        return [
            'id' => $c->id,
            'title' => $c->title,
            'url' => route('courses.show', $c),
            'chat_url' => route('chat.course', $c),
            'image_url' => $c->imageUrl(),
        ];
    }
}
