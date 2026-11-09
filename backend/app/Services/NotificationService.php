<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * In-app notifications stored in MySQL (Version 1.0).
 * SMS / WhatsApp can be added later by extending sendExternal().
 */
class NotificationService
{
    public function notify(iterable|User|int|null $users, string $title, string $message, string $type, array $data = []): void
    {
        if ($users === null) return;
        $ids = collect(is_iterable($users) ? $users : [$users])
            ->map(fn ($u) => $u instanceof User ? $u->id : $u)->filter()->unique();
        foreach ($ids as $id) {
            AppNotification::create(['user_id' => $id, 'title' => $title, 'message' => $message, 'type' => $type, 'data_json' => $data]);
        }
    }

    public function notifyAdmins(string $title, string $message, string $type, array $data = []): void
    {
        $this->notify(User::where('role', 'admin')->where('status', 'active')->pluck('id'), $title, $message, $type, $data);
    }

    /** Placeholder hook for an SMS/WhatsApp provider. Logs only until a provider is approved. */
    public function sendSms(string $mobile, string $text): void
    {
        Log::channel(config('logging.default'))->info('[SMS] to ' . $mobile . ': ' . $text);
    }
}
