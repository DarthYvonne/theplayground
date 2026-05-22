<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unreadCount(Request $request): JsonResponse
    {
        $u = $request->user();
        if (!$u) return response()->json(['notifications' => 0, 'messages' => 0]);
        return response()->json([
            'notifications' => $u->unreadNotificationCount(),
            'messages' => $u->unreadMessageCount(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $u = $request->user();
        $items = $u->notifications()->with('actor','course')->limit(30)->get()->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'link' => $n->link,
            'time_human' => $n->created_at->diffForHumans(),
            'read' => (bool) $n->read_at,
            'actor' => $n->actor ? [
                'name' => $n->actor->name,
                'initials' => $n->actor->initials(),
                'picture_url' => $n->actor->pictureUrl(),
            ] : null,
        ]);
        return response()->json(['notifications' => $items]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }
}
