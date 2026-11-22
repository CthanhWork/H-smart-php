<?php

namespace App\Modules\Order\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
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
                $codAwaitingConfirmation = $order->payment_method === 'cod' && $order->status === Order::STATUS_PAID && $order->payment_status === 'paid';
                $legacyCodAwaitingConfirmation = $order->payment_method === 'cod' && (int) $order->cod_deposit_amount === 0
                    && $order->status === Order::STATUS_PENDING_PAYMENT && $order->payment_status === 'pending';
                if ($order->status !== Order::STATUS_PAID && ! $codAwaitingConfirmation && ! $legacyCodAwaitingConfirmation) {
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
                    'delivery_status' => $order->payment_method === 'cod' ? 'in_transit' : $order->delivery_status,
                ]);
            } elseif ($action === 'complete') {
                abort_unless($order->buyer_id === $userId, 404);
                if (! in_array($order->status, [Order::STATUS_SHIPPING, Order::STATUS_COMPLETED], true) || $order->payout_status === 'credited_to_wallet') {
                    throw new ConflictHttpException('Đơn hàng chưa thể xác nhận đã nhận hoặc đã được giải ngân vào số dư.');
                }
                if ($order->disputes()->where('status', 'open')->exists()) {
                    throw new ConflictHttpException('Đơn đang có khiếu nại; hãy chờ quản trị viên xử lý.');
                }
                $confirmedAt = $order->buyer_confirmed_at ?? now();
                $deliveredAt = $order->delivered_at ?? $confirmedAt;
                $releaseEligibleAt = $deliveredAt->copy()->addHours(48);
                if ($order->release_eligible_at && $order->release_eligible_at->greaterThan($releaseEligibleAt)) {
                    $releaseEligibleAt = $order->release_eligible_at;
                }
                $payoutStatus = $order->payment_status !== 'paid' ? 'not_ready'
                    : ($order->payment_method === 'cod' && $order->cod_remaining_collection_type !== 'direct' && ! $order->carrier_settled_at ? 'awaiting_carrier_settlement' : 'awaiting_dispute_window');
                $order->update([
                    'status' => Order::STATUS_COMPLETED,
                    'completed_at' => $order->completed_at ?? $confirmedAt,
                    'buyer_confirmed_at' => $confirmedAt,
                    'delivered_at' => $deliveredAt,
                    'release_eligible_at' => $releaseEligibleAt,
                    'delivery_status' => 'buyer_confirmed',
                    'payout_status' => $payoutStatus,
                ]);
            } elseif ($action === 'return_received') {
                abort_unless($order->seller_id === $userId, 404);
                if ($order->payment_method !== 'cod' || $order->status !== Order::STATUS_SHIPPING || $order->delivery_status !== 'returned_to_seller') {
                    throw new ConflictHttpException('Đơn chưa được ghi nhận hoàn hàng về người bán.');
                }
                $product = Product::lockForUpdate()->findOrFail($order->product_id);
                $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]);
                if (in_array($product->status, [Product::STATUS_RESERVED, Product::STATUS_SOLD], true)) {
                    $product->update(['status' => Product::STATUS_ACTIVE]);
                }
                if ($order->offer_id) {
                    Offer::whereKey($order->offer_id)->where('status', Offer::STATUS_ORDERED)->update(['status' => Offer::STATUS_ACCEPTED]);
                }
            }

            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'statusHistory']);
        });
    }
}
