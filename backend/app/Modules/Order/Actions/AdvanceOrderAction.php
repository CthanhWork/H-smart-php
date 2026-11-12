<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Offer\Models\Offer;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdvanceOrderAction
{
    public function execute(int $orderId, int $userId, string $action, array $shipment = []): Order
    {
        return DB::transaction(function () use ($orderId, $userId, $action, $shipment): Order {
            $order = Order::lockForUpdate()->findOrFail($orderId);

            if ($action === 'confirm') {
                abort_unless($order->seller_id === $userId, 404);
                $codAwaitingConfirmation = $order->payment_provider === 'cod' && $order->status === Order::STATUS_PENDING_PAYMENT;
                if ($order->status !== Order::STATUS_PAID && ! $codAwaitingConfirmation) {
                    throw new ConflictHttpException('Đơn cần được thanh toán hoặc đặt theo COD trước khi xác nhận.');
                }
                $order->update(['status' => Order::STATUS_PROCESSING, 'confirmed_at' => now()]);
            } elseif ($action === 'ship') {
                abort_unless($order->seller_id === $userId, 404);
                if ($order->status !== Order::STATUS_PROCESSING) {
                    throw new ConflictHttpException('Đơn hàng chưa sẵn sàng để gửi.');
                }
                $order->update([
                    'status' => Order::STATUS_SHIPPING,
                    'shipped_at' => now(),
                    'shipping_carrier' => $shipment['shipping_carrier'],
                    'tracking_number' => $shipment['tracking_number'] ?? null,
                    'delivery_status' => $order->payment_provider === 'cod' ? 'in_transit' : $order->delivery_status,
                ]);
            } elseif ($action === 'complete') {
                abort_unless($order->buyer_id === $userId, 404);
                if ($order->status !== Order::STATUS_SHIPPING) {
                    throw new ConflictHttpException('Chỉ có thể hoàn tất đơn đang giao.');
                }
                $order->update(['status' => Order::STATUS_COMPLETED, 'completed_at' => now()]);
            } elseif ($action === 'return_received') {
                abort_unless($order->seller_id === $userId, 404);
                if ($order->payment_provider !== 'cod' || $order->status !== Order::STATUS_SHIPPING || $order->delivery_status !== 'returned_to_seller') {
                    throw new ConflictHttpException('Đơn chưa được ghi nhận hoàn hàng về người bán.');
                }
                $product = Product::lockForUpdate()->findOrFail($order->product_id);
                $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]);
                if (in_array($product->status, [Product::STATUS_RESERVED, Product::STATUS_SOLD], true)) $product->update(['status' => Product::STATUS_ACTIVE]);
                if ($order->offer_id) Offer::whereKey($order->offer_id)->where('status', Offer::STATUS_ORDERED)->update(['status' => Offer::STATUS_ACCEPTED]);
            }

            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'statusHistory']);
        });
    }
}
