<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderDispute;
use App\Modules\Order\Models\OrderDisputeEvent;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminDisputeController
{
    public function index(): JsonResponse
    {
        $disputes = OrderDispute::query()->with(['opener:id,username,full_name,email', 'events.actor:id,username,full_name'])
            ->whereIn('status', ['open', 'reconciliation_pending'])->with('order.product.images', 'order.buyer', 'order.seller')
            ->latest('id')->paginate(30);
        return response()->json(['status' => 'success', 'message' => 'Khiếu nại đơn hàng cần xử lý.', 'data' => [
            'content' => $disputes->getCollection()->map(fn (OrderDispute $dispute) => $this->resource($dispute))->values(),
            'page' => $disputes->currentPage(), 'total' => $disputes->total(),
        ]]);
    }

    public function resolve(Request $request, int $id, WalletLedger $ledger, AdminAuditLogger $audit, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'resolution' => ['required', 'in:release_to_seller,refund_to_buyer_wallet,manual_reconciliation'],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        [$dispute, $order] = DB::transaction(function () use ($request, $id, $data, $ledger, $audit): array {
            $disputeSnapshot = OrderDispute::findOrFail($id);
            $order = Order::lockForUpdate()->findOrFail($disputeSnapshot->order_id);
            $dispute = OrderDispute::lockForUpdate()->findOrFail($id);
            if (! in_array($dispute->status, ['open', 'reconciliation_pending'], true)) throw new ConflictHttpException('Khiếu nại này đã được xử lý.');
            if ($order->payout_status === 'credited_to_wallet' || $order->seller_paid_at !== null) throw new ConflictHttpException('Đơn đã giải ngân; không thể thay đổi quyết định xử lý tiền.');

            if ($data['resolution'] === 'manual_reconciliation') {
                $dispute->update(['status' => 'reconciliation_pending', 'resolution' => null, 'resolution_note' => $data['note']]);
                $order->update(['payout_status' => 'held']);
            } elseif ($data['resolution'] === 'release_to_seller') {
                if ($order->payment_status !== 'paid') throw new ConflictHttpException('Chưa xác nhận đã thu đủ tiền cho đơn.');
                if ($order->payment_method === 'cod' && $order->cod_remaining_collection_type !== 'direct' && ! $order->carrier_settled_at) throw new ConflictHttpException('Đơn COD chưa được đơn vị giao nhận đối soát tiền về.');
                $dispute->update(['status' => 'resolved', 'resolution' => 'release_to_seller', 'resolution_note' => $data['note'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
                $order->update([
                    'status' => Order::STATUS_COMPLETED, 'completed_at' => $order->completed_at ?? now(),
                    'delivered_at' => $order->delivered_at ?? now(), 'release_eligible_at' => now(), 'payout_status' => 'eligible',
                ]);
            } else {
                if (! $dispute->buyer_wallet_refund_consent_at) throw new ConflictHttpException('Người mua chưa đồng ý nhận khoản hoàn vào số dư H-Smart.');
                if ($order->payment_status !== 'paid') throw new ConflictHttpException('Chưa xác nhận đã thu đủ tiền cho đơn.');
                if ($order->payment_method === 'cod' && $order->cod_remaining_collection_type !== 'direct' && ! $order->carrier_settled_at) throw new ConflictHttpException('Chưa đối soát được tiền COD; chưa thể ghi nhận hoàn tiền.');
                $ledger->refundOrderToBuyerWallet($order, (int) $request->user()->id, $data['note']);
                $dispute->update(['status' => 'resolved', 'resolution' => 'refund_to_buyer_wallet', 'resolution_note' => $data['note'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
                $order->update(['payout_status' => 'refunded_to_wallet']);
            }

            OrderDisputeEvent::create([
                'dispute_id' => $dispute->id, 'actor_id' => $request->user()->id,
                'action' => $data['resolution'], 'note' => $data['note'],
                'metadata' => ['resolution' => $data['resolution']],
            ]);
            $audit->record($request->user(), 'order_dispute_'.$data['resolution'], 'order_dispute', $dispute->id,
                'Quản trị viên xử lý khiếu nại đơn hàng.', ['order_id' => $order->id, 'note' => $data['note']]);
            return [$dispute->refresh(), $order->refresh()];
        }, 3);

        $title = $data['resolution'] === 'refund_to_buyer_wallet' ? 'Đã hoàn tiền về số dư mô phỏng' : ($data['resolution'] === 'release_to_seller' ? 'Khiếu nại đã được xử lý' : 'Khiếu nại đang chờ đối soát');
        $body = match ($data['resolution']) {
            'refund_to_buyer_wallet' => 'Đơn #'.$order->id.' được hoàn vào số dư H-Smart theo lựa chọn đã đồng ý của người mua.',
            'release_to_seller' => 'Đơn #'.$order->id.' đủ điều kiện ghi có tiền bán hàng vào số dư người bán.',
            default => 'Đơn #'.$order->id.' đang được giữ nguyên trong khi chờ đối soát.',
        };
        $notifications->send($order->buyer, $request->user(), 'order_dispute_resolved', $title, $body, '/me/orders', ['order_id' => $order->id, 'dispute_id' => $dispute->id]);
        $notifications->send($order->seller, $request->user(), 'order_dispute_resolved', $title, $body, '/me/orders', ['order_id' => $order->id, 'dispute_id' => $dispute->id]);
        return response()->json(['status' => 'success', 'message' => $title.'. '.$body, 'data' => $this->resource($dispute)]);
    }

    private function resource(OrderDispute $dispute): array
    {
        $order = $dispute->order;
        return [
            'id' => $dispute->id, 'status' => $dispute->status, 'reason' => $dispute->reason,
            'details' => $dispute->details, 'evidence_notes' => $dispute->evidence_notes,
            'resolution' => $dispute->resolution, 'resolution_note' => $dispute->resolution_note,
            'buyer_wallet_refund_consent_at' => $dispute->buyer_wallet_refund_consent_at?->toIso8601String(),
            'created_at' => $dispute->created_at?->toIso8601String(),
            'opener' => $dispute->opener ? ['id' => $dispute->opener->id, 'name' => $dispute->opener->full_name ?: $dispute->opener->username] : null,
            'events' => $dispute->events->map(fn ($event) => ['action' => $event->action, 'note' => $event->note, 'created_at' => $event->created_at?->toIso8601String()])->values(),
            'order' => $order ? [
                'id' => $order->id, 'status' => $order->status, 'payment_status' => $order->payment_status,
                'payment_provider' => $order->payment_provider, 'payout_status' => $order->payout_status,
                'product_amount' => $order->product_amount, 'shipping_fee' => $order->shipping_fee,
                'platform_fee' => $order->platform_fee, 'total_amount' => $order->total_amount,
                'buyer' => $order->buyer?->only(['id', 'username', 'full_name', 'email']),
                'seller' => $order->seller?->only(['id', 'username', 'full_name', 'email']),
                'product' => $order->product?->only(['id', 'title', 'status']),
            ] : null,
        ];
    }
}
