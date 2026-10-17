<?php

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Models\InAppNotification;
use App\Modules\User\Models\User;

class NotificationService
{
    public function send(User $recipient, ?User $actor, string $type, string $title, string $body, string $actionUrl, array $data = []): ?InAppNotification
    {
        if ($actor && $recipient->id === $actor->id) {
            return null;
        }

        return InAppNotification::create([
            'recipient_id' => $recipient->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'data' => $data ?: null,
        ]);
    }

    public function name(User $user): string
    {
        return $user->full_name ?: $user->username;
    }
}
