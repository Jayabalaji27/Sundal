<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $this->workspaceNotifications($user)
            ->latest()
            ->paginate(20)
            ->through(fn (DatabaseNotification $notification) => $this->format($notification));

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        $record = $this->workspaceNotifications($request->user())
            ->where('id', $notification)
            ->firstOrFail();

        $record->markAsRead();

        return back();
    }

    public function markAllRead(Request $request)
    {
        $this->workspaceNotifications($request->user())
            ->whereNull('read_at')
            ->get()
            ->each->markAsRead();

        return back();
    }

    private function workspaceNotifications($user): MorphMany
    {
        return $user->notifications()->where('data->workspace_id', $user->current_workspace_id);
    }

    public static function format(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? '',
            'content' => $data['content'] ?? '',
            'link' => $data['link'] ?? null,
            'sender_name' => $data['sender_name'] ?? null,
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at,
        ];
    }
}
