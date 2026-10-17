<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Models\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $notifications = InAppNotification::with('actor:id,username,full_name')
            ->where('recipient_id', $userId)
            ->latest('id')
            ->paginate(min(max((int) $request->input('per_page', 30), 1), 100));

        return response()->json([
            'status' => 'success',
            'message' => 'Thông báo của bạn.',
            'data' => [
                'items' => $notifications->getCollection()->map(fn (InAppNotification $item): array => $this->resource($item))->values(),
                'unread_count' => InAppNotification::where('recipient_id', $userId)->whereNull('read_at')->count(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = InAppNotification::where('recipient_id', $request->user()->id)->whereNull('read_at')->count();
        return response()->json(['status' => 'success', 'message' => 'Số thông báo chưa đọc.', 'data' => ['unread_count' => $count]]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = InAppNotification::where('recipient_id', $request->user()->id)->findOrFail($id);
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return response()->json(['status' => 'success', 'message' => 'Đã đánh dấu đã đọc.', 'data' => ['id' => $notification->id, 'read_at' => $notification->read_at?->toIso8601String()]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        InAppNotification::where('recipient_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['status' => 'success', 'message' => 'Đã đánh dấu tất cả thông báo đã đọc.', 'data' => null]);
    }

    private function resource(InAppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_url' => $notification->action_url,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
            'actor' => $notification->actor ? ['id' => $notification->actor->id, 'name' => $notification->actor->full_name ?: $notification->actor->username] : null,
        ];
    }
}
