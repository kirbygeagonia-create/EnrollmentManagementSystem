<?php

namespace App\Http\Controllers;

use App\Models\Notifications;
use App\Models\Staffusers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Return the current staff user's notifications (JSON for the bell).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $notifications = Notifications::where('notifiable_type', Staffusers::class)
            ->where('notifiable_id', $user->userId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Notifications $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data ?? [],
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        $unreadCount = Notifications::where('notifiable_type', Staffusers::class)
            ->where('notifiable_id', $user->userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, Notifications $notification): JsonResponse
    {
        $user = Auth::user();

        if ($notification->notifiable_type === Staffusers::class && $notification->notifiable_id === $user->userId) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Mark all of the current staff user's notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = Auth::user();

        Notifications::where('notifiable_type', Staffusers::class)
            ->where('notifiable_id', $user->userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
