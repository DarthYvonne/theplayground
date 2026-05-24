<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Message;
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
            ->values()
            ->map(fn ($i) => collect($i)->except('sort_key')->all());

        return response()->json(['items' => $merged]);
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
