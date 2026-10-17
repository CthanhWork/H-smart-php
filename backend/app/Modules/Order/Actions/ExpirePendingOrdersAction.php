<?php

namespace App\Modules\Order\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;

class ExpirePendingOrdersAction
{
    public function execute(NotificationService $notifications): int
    {
        $orderIds = Order::where('status', Order::STATUS_PENDING_PAYMENT)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');
        $expiredCount = 0;

        foreach ($orderIds as $orderId) {
            $expired = DB::transaction(function () use ($orderId): bool {
                $snapshot = Order::find($orderId);
                if (! $snapshot) {
                    return false;
                }

                $product = Product::lockForUpdate()->find($snapshot->product_id);
                $order = Order::lockForUpdate()->find($orderId);
                if (! $product || ! $order
                    || $order->status !== Order::STATUS_PENDING_PAYMENT
                    || $order->expires_at->isFuture()) {
                    return false;
                }

                if ($order->payment_status !== 'pending') {
                    return false;
                }

                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'payment_status' => 'expired',
                ]);
                if ($product->status === Product::STATUS_RESERVED) {
                    $product->update(['status' => Product::STATUS_ACTIVE]);
                }
                if ($order->offer_id) {
                    Offer::whereKey($order->offer_id)
                        ->where('status', Offer::STATUS_ORDERED)
                        ->update(['status' => Offer::STATUS_ACCEPTED]);
                }

                return true;
            });

            if ($expired) {
                $expiredCount++;
                $order = Order::with(['buyer', 'seller', 'product'])->find($orderId);
                if ($order) {
                    $body = 'Đơn hàng #'.$order->id.' đã hết hạn thanh toán; sản phẩm được mở bán lại.';
                    $notifications->send($order->buyer, null, 'order_expired', 'Đơn hàng đã hết hạn', $body, '/me/orders', ['order_id' => $order->id]);
                    $notifications->send($order->seller, null, 'order_expired', 'Đơn hàng đã hết hạn', $body, '/me/orders', ['order_id' => $order->id]);
                }
            }
        }

        return $expiredCount;
    }
}
