<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminDeliveryController
{
    public function confirm(Request $request, int $id, AdminAuditLogger $audit, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['evidence' => ['required', 'string', 'min:8', 'max:2000']]);
        $order = DB::transaction(function () use ($request, $id, $audit, $data): Order {
            $order = Order::lockForUpdate()->findOrFail($id);
            if ($order->payment_method === 'cod' || $order->status !== Order::STATUS_SHIPPING || $order->delivered_at) {
                throw new ConflictHttpException('Chỉ có thể xác nhận một lần cho đơn không COD đang giao.');
            }
            if ($order->payment_status !== 'paid') throw new ConflictHttpException('Chưa xác nhận đã nhận đủ tiền cho đơn.');
            $now = now();
            $order->update([
                'delivered_at' => $now,
                'delivery_status' => 'delivered_verified',
                'release_eligible_at' => $now->copy()->addHours(48),
                'payout_status' => 'awaiting_dispute_window',
            ]);
            $audit->record($request->user(), 'delivery_success_verified', 'order', $order->id,
                'Quản trị viên xác nhận giao thành công; bắt đầu thời hạn 48 giờ.', ['evidence' => $data['evidence']]);
            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'statusHistory', 'disputes']);
        }, 3);
        $notifications->send($order->buyer, $request->user(), 'delivery_success_verified', 'Giao hàng đã được xác nhận', 'Quản trị viên đã xác nhận giao thành công cho đơn #'.$order->id.'. Thời hạn khiếu nại 48 giờ bắt đầu từ thời điểm này.', '/me/orders', ['order_id' => $order->id, 'release_eligible_at' => $order->release_eligible_at?->toIso8601String()]);
        $notifications->send($order->seller, $request->user(), 'delivery_success_verified', 'Giao hàng đã được xác nhận', 'Quản trị viên đã xác nhận giao thành công cho đơn #'.$order->id.'. Tiền bán hàng chờ hết thời hạn khiếu nại 48 giờ.', '/me/orders', ['order_id' => $order->id, 'release_eligible_at' => $order->release_eligible_at?->toIso8601String()]);
        return response()->json(['status' => 'success', 'message' => 'Đã lưu bằng chứng giao thành công; thời hạn khiếu nại/giải ngân bắt đầu từ thời điểm này.', 'data' => new OrderResource($order)]);
    }
}
