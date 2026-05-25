<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Message;
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
        Message::with('user')
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

        $payload = $merged->map(fn ($i) => collect($i)->except('sort_key')->all())->values();

        return response()->json(['items' => $payload]);
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
