<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminCodController extends Controller
{
    public function update(Request $request, int $id, WalletLedger $ledger, AdminAuditLogger $audit, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:delivered,carrier_settled,seller_paid,delivery_failed,retry_delivery,returned,refund_deposit'],
            'collection_type' => ['required_if:action,delivered', 'nullable', 'in:carrier,direct'],
            'evidence' => ['required_if:action,delivered,carrier_settled', 'nullable', 'string', 'min:5', 'max:1000'],
            'reason' => ['required_if:action,delivery_failed,refund_deposit', 'nullable', 'string', 'min:3', 'max:500'],
        ]);

        $order = DB::transaction(function () use ($request, $id, $data, $ledger, $audit): Order {
            $order = Order::lockForUpdate()->findOrFail($id);
            if ($order->payment_method !== 'cod') {
                throw new ConflictHttpException('Thao tác này chỉ áp dụng cho đơn COD.');
            }

            $now = now();
            if ($data['action'] === 'delivered') {
                $depositVerified = (int) $order->cod_deposit_amount === 0 ? in_array($order->payment_status, ['pending', 'paid'], true) : $order->payment_status === 'paid';
                if ($order->status !== Order::STATUS_SHIPPING || ! $depositVerified || $order->cod_collected_at) {
                    throw new ConflictHttpException('Đơn COD cần được cọc và đang giao trước khi xác nhận giao và thu tiền.');
                }
                $order->update([
                    'status' => Order::STATUS_COMPLETED, 'completed_at' => $now, 'delivered_at' => $now,
                    'release_eligible_at' => $now->copy()->addHours(48), 'payout_status' => 'awaiting_carrier_settlement',
                    'cod_collected_at' => $now, 'delivery_status' => 'delivered', 'delivery_failed_reason' => null,
                    'cod_remaining_collected_at' => $now, 'cod_remaining_collection_type' => $data['collection_type'],
                    'payment_status' => 'paid', 'payment_paid_at' => $now,
                ]);
                Product::whereKey($order->product_id)->lockForUpdate()->update(['status' => Product::STATUS_SOLD]);
            } elseif ($data['action'] === 'carrier_settled') {
                if (! $order->cod_collected_at || ! $order->cod_remaining_collected_at || $order->cod_remaining_collection_type !== 'carrier' || $order->carrier_settled_at) {
                    throw new ConflictHttpException('Đơn chưa thu đủ COD qua đơn vị vận chuyển hoặc đã được đối soát.');
                }
                if ((int) $order->cod_deposit_amount === 0) {
                    $ledger->bookOrderEscrow($order, 'cod_carrier_settlement');
                }
                $order->update([
                    'carrier_settled_at' => $now,
                    'payout_status' => $order->disputes()->where('status', 'open')->exists() ? 'held'
                        : ($order->buyer_confirmed_at || ($order->release_eligible_at && $order->release_eligible_at->lte($now)) ? 'eligible' : 'awaiting_dispute_window'),
                ]);
            } elseif ($data['action'] === 'seller_paid') {
                if (! $order->cod_collected_at || ! $order->cod_remaining_collected_at
                    || ($order->cod_remaining_collection_type !== 'direct' && ! $order->carrier_settled_at) || ! in_array($order->payout_status, ['eligible', 'awaiting_dispute_window'], true)
                    || ! $order->release_eligible_at || $order->release_eligible_at->isFuture()
                    || $order->disputes()->where('status', 'open')->exists()) {
                    throw new ConflictHttpException('COD chưa đối soát, chưa qua thời hạn giao hoặc đang có khiếu nại.');
                }
                $ledger->releaseOrderToWallet($order);
                $order->update(['payout_status' => 'credited_to_wallet', 'seller_paid_at' => $now, 'wallet_credited_at' => $now]);
            } elseif ($data['action'] === 'delivery_failed') {
                if ($order->status !== Order::STATUS_SHIPPING || $order->cod_collected_at) {
                    throw new ConflictHttpException('Đơn không ở trạng thái đang giao.');
                }
                $order->update(['delivery_status' => 'failed', 'delivery_failed_reason' => $data['reason']]);
            } elseif ($data['action'] === 'retry_delivery') {
                if ($order->delivery_status !== 'failed' || $order->status !== Order::STATUS_SHIPPING) {
                    throw new ConflictHttpException('Chỉ có thể giao lại đơn đã ghi nhận giao thất bại.');
                }
                $order->update(['delivery_status' => 'in_transit', 'delivery_failed_reason' => null]);
            } elseif ($data['action'] === 'returned') {
                if ($order->status !== Order::STATUS_SHIPPING || $order->delivery_status !== 'failed') {
                    throw new ConflictHttpException('Chỉ ghi nhận hoàn hàng sau khi giao thất bại.');
                }
                $order->update(['delivery_status' => 'returned_to_seller', 'returned_at' => $now]);
            } else {
                if ($order->status !== Order::STATUS_CANCELLED || $order->payment_status !== 'paid' || $order->delivery_status !== 'returned_to_seller' || $order->cod_deposit_refunded_at || ! $order->cod_deposit_wallet_consent_at) {
                    throw new ConflictHttpException('Cần xác nhận hàng đã hoàn về người bán, tiền cọc đã xác minh và người mua đồng ý trước khi hoàn.');
                }
                $ledger->refundCodDepositToBuyer($order, (int) $request->user()->id, (string) $data['reason']);
                $order->update(['payment_status' => 'refunded', 'payout_status' => 'refunded', 'cod_deposit_refunded_at' => $now]);
            }

            $audit->record($request->user(), 'cod_'.$data['action'], 'order', $order->id,
                'Quản trị viên cập nhật trạng thái giao hàng/đối soát COD.',
                ['evidence' => $data['evidence'] ?? null, 'reason' => $data['reason'] ?? null, 'reference' => $order->payment_txn_ref]);

            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'statusHistory']);
        });

        $messages = match ($data['action']) {
            'delivered' => ['Đơn COD đã giao', 'Đã xác nhận giao thành công và thu COD cho đơn #'.$order->id.'. Thời hạn khiếu nại 48 giờ bắt đầu từ đây.'],
            'refund_deposit' => ['Đã hoàn tiền cọc COD', 'Tiền cọc của đơn #'.$order->id.' đã được hoàn vào số dư mô phỏng của người mua.'],
            'carrier_settled' => ['Đã đối soát COD', 'Khoản COD của đơn #'.$order->id.' đã được ghi nhận về sổ nội bộ.'],
            'seller_paid' => ['Tiền bán hàng đã vào số dư', 'Tiền thuần của đơn #'.$order->id.' đã được ghi có vào số dư H-Smart mô phỏng.'],
            'delivery_failed' => ['Giao hàng chưa thành công', 'Đơn #'.$order->id.' được ghi nhận giao thất bại: '.($data['reason'] ?? '').'.'],
            'retry_delivery' => ['Đơn hàng đang giao lại', 'Đơn #'.$order->id.' được ghi nhận đang giao lại.'],
            default => ['Đơn hàng được hoàn về', 'Đơn #'.$order->id.' được ghi nhận đã hoàn về người bán.'],
        };
        foreach ([$order->buyer, $order->seller] as $recipient) {
            $notifications->send($recipient, $request->user(), 'cod_'.$data['action'], $messages[0], $messages[1], '/me/orders', ['order_id' => $order->id]);
        }

        return response()->json(['status' => 'success', 'message' => 'Đã cập nhật đối soát COD.', 'data' => new OrderResource($order)]);
    }
}
