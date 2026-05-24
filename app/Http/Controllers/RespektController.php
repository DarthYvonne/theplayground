<?php

namespace App\Http\Controllers;

use App\Models\Respekt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RespektController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:platform_message,course_message,enrollment'],
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
        }

        $count = Respekt::where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])
            ->count();

        return response()->json([
            'respekted' => $respekted,
            'count' => $count,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:platform_message,course_message,enrollment'],
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
