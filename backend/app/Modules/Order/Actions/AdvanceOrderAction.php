<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;
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
                if ($order->status !== Order::STATUS_PAID) {
                    throw new ConflictHttpException('Chỉ có thể xác nhận đơn đã thanh toán.');
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
                ]);
            } elseif ($action === 'complete') {
                abort_unless($order->buyer_id === $userId, 404);
                if ($order->status !== Order::STATUS_SHIPPING) {
                    throw new ConflictHttpException('Chỉ có thể hoàn tất đơn đang giao.');
                }
                $order->update(['status' => Order::STATUS_COMPLETED, 'completed_at' => now()]);
            }

            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'statusHistory']);
        });
    }
}
