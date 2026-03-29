<?php

namespace App\Services\Api;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserNotificationApiService
{
    public function unreadForUser(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->unread()
            ->active()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function markRead(int $userId, int $notificationId): Notification
    {
        $notification = $this->findForUser($userId, $notificationId);
        $notification->markRead();

        return $notification->refresh();
    }

    public function dismiss(int $userId, int $notificationId): Notification
    {
        $notification = $this->findForUser($userId, $notificationId);
        $notification->dismiss();

        return $notification->refresh();
    }

    private function findForUser(int $userId, int $notificationId): Notification
    {
        $notification = Notification::query()
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            throw new ModelNotFoundException('Notification not found for user.');
        }

        return $notification;
    }
}
