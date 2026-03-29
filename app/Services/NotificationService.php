<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function notifyAll(
        string $type,
        string $severity,
        string $title,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $meta = null,
        ?string $actionUrl = null,
        bool $isActionable = false,
    ): void {
        $users = User::where('is_active', true)->get();

        foreach ($users as $user) {
            if (!$user->wantsNotification($type)) {
                continue;
            }

            Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'meta' => $meta,
                'action_url' => $actionUrl,
                'is_actionable' => $isActionable,
                'created_at' => now(),
            ]);
        }
    }

    public function notifyUser(int $userId, string $type, string $severity, string $title, string $message, ?array $meta = null): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
