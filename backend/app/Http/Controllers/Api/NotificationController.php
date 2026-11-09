<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->user()->appNotifications()->latest();
        if ($request->boolean('unread')) $q->whereNull('read_at');
        return $this->ok($q->paginate($this->perPage()), 'OK', 200, ['unread' => $request->user()->appNotifications()->whereNull('read_at')->count()]);
    }

    public function read(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return $this->ok($notification);
    }

    public function readAll(Request $request)
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);
        return $this->ok(null, 'All marked as read');
    }
}
