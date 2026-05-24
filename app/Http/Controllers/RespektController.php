<?php

namespace App\Http\Controllers;

use App\Models\Respekt;
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
}
