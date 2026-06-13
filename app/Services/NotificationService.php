<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public function sendNotification(array $data): void
    {
        Notification::create([
            'customer_id' => $data['customer_id'],
            'user_id' => $data['user_id'] ?? auth()->id(),
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? null,
            'channel' => $data['channel'] ?? 'system',
            'is_read' => $data['is_read'] ?? false,
            'sent_at' => $data['sent_at'] ?? now(),
        ]);
    }
}
