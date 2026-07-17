<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentNotificationController extends Controller
{
    /**
     * GET /api/agent/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $notifications = Notification::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => $this->format($n));

        $unreadCount = Notification::where('user_id', $userId)
            ->where('lu', false)
            ->count();

        return response()->json([
            'success'      => true,
            'unread_count' => $unreadCount,
            'data'         => $notifications,
        ]);
    }

    /**
     * PATCH /api/agent/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update([
            'lu'    => true,
            'lu_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue.',
        ]);
    }

    /**
     * POST /api/agent/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('lu', false)
            ->update([
                'lu'    => true,
                'lu_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues.',
        ]);
    }

    private function format(Notification $n): array
    {
        return [
            'id'         => $n->id,
            'type'       => $n->type,
            'titre'      => $n->titre,
            'message'    => $n->message,
            'data'       => $n->data,
            'lu'         => $n->lu,
            'lu_at'      => $n->lu_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
