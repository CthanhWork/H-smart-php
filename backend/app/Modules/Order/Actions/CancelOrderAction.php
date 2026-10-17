<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Offer\Models\Offer;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelOrderAction
{
    public function execute(int $orderId, int $buyerId): Order
    {
        return DB::transaction(function () use ($orderId, $buyerId): Order {
            $snapshot = Order::findOrFail($orderId);
            $product = Product::lockForUpdate()->findOrFail($snapshot->product_id);
            $order = Order::lockForUpdate()->findOrFail($orderId);
            abort_unless($order->buyer_id === $buyerId, 404);
            if ($order->status !== Order::STATUS_PENDING_PAYMENT) {
                throw new ConflictHttpException('Chỉ có thể hủy đơn đang chờ thanh toán.');
            }

            if ($order->payment_status !== 'pending') {
                throw new ConflictHttpException('Trạng thái thanh toán không cho phép hủy đơn.');
            }
            $order->update(['payment_status' => 'failed', 'payment_response_code' => 'CANCELLED', 'status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]);
            if ($product->status === Product::STATUS_RESERVED) {
                $product->update(['status' => Product::STATUS_ACTIVE]);
            }
            if ($order->offer_id) {
                Offer::whereKey($order->offer_id)->where('status', Offer::STATUS_ORDERED)->update(['status' => Offer::STATUS_ACCEPTED]);
            }

            return $order->load(['product.images', 'buyer', 'seller', 'offer']);
        });
    }
}
