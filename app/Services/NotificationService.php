<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Create a notification. customer_id null = platform-wide; user_id null =
     * visible to the whole company.
     */
    public function notify(string $type, string $title, string $message, ?int $customerId = null, ?int $userId = null): Notification
    {
        return Notification::create([
            'customer_id' => $customerId,
            'user_id' => $userId,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
        ]);
    }

    /**
     * Create a notification only if an identical unread one does not already
     * exist, so repeated generator runs don't flood the inbox.
     */
    public function notifyOnce(string $type, string $title, string $message, ?int $customerId = null, ?int $userId = null): ?Notification
    {
        $exists = Notification::withoutGlobalScopes()
            ->where('notification_type', $type)
            ->where('title', $title)
            ->where('customer_id', $customerId)
            ->where('is_read', false)
            ->exists();

        if ($exists) {
            return null;
        }

        return $this->notify($type, $title, $message, $customerId, $userId);
    }
}
