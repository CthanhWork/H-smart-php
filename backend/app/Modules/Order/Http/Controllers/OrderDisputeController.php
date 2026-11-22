<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderDispute;
use App\Modules\Order\Models\OrderDisputeEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderDisputeController
{
    public function acceptCodDepositWalletRefund(Request $request, int $id): JsonResponse
    {
        $request->validate(['accept' => ['required', 'accepted']]);
        $order = DB::transaction(function () use ($request, $id): Order {
            $order = Order::lockForUpdate()->findOrFail($id);
            abort_unless($order->buyer_id === (int) $request->user()->id, 404);
            if ($order->payment_method !== 'cod' || $order->status !== Order::STATUS_CANCELLED || $order->delivery_status !== 'returned_to_seller' || $order->payment_status !== 'paid' || $order->cod_deposit_refunded_at) {
                throw new ConflictHttpException('Đơn chưa đủ điều kiện chọn hoàn cọc vào số dư.');
            }
            $order->update(['cod_deposit_wallet_consent_at' => now()]);
            return $order;
        }, 3);

        return response()->json(['status' => 'success', 'message' => 'Đã lưu ý kiến đồng ý nhận hoàn cọc vào số dư H-Smart mô phỏng. Quản trị viên vẫn cần xác minh và xử lý.', 'data' => new \App\Modules\Order\Http\Resources\OrderResource($order)]);
    }

    public function store(Request $request, int $id, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:100'],
            'details' => ['required', 'string', 'min:10', 'max:5000'],
            'evidence_notes' => ['sometimes', 'array', 'max:5'],
            'evidence_notes.*' => ['string', 'min:3', 'max:1000'],
        ]);
        [$dispute, $order] = DB::transaction(function () use ($request, $id, $data): array {
            $order = Order::with(['buyer', 'seller'])->lockForUpdate()->findOrFail($id);
            abort_unless($order->buyer_id === (int) $request->user()->id, 404);
            if (! in_array($order->status, [Order::STATUS_SHIPPING, Order::STATUS_COMPLETED], true)
                || $order->payment_status !== 'paid'
                || $order->payout_status === 'credited_to_wallet'
                || $order->seller_paid_at !== null) {
                throw new ConflictHttpException('Đơn chưa đủ điều kiện mở khiếu nại hoặc tiền đã được giải ngân.');
            }
            if ($order->disputes()->where('status', 'open')->exists()) throw new ConflictHttpException('Đơn này đã có khiếu nại đang mở.');
            $dispute = OrderDispute::create([
                'order_id' => $order->id, 'opened_by' => $request->user()->id, 'status' => 'open',
                'reason' => trim($data['reason']), 'details' => trim($data['details']),
                'evidence_notes' => array_values($data['evidence_notes'] ?? []),
            ]);
            OrderDisputeEvent::create(['dispute_id' => $dispute->id, 'actor_id' => $request->user()->id, 'action' => 'opened', 'note' => $dispute->details]);
            $order->update(['payout_status' => 'held']);
            return [$dispute, $order];
        }, 3);
        $notifications->send($order->seller, $request->user(), 'order_dispute_opened', 'Có khiếu nại về đơn hàng', 'Người mua đã mở khiếu nại cho đơn #'.$order->id.'. Số tiền liên quan đang tạm giữ.', '/me/orders', ['order_id' => $order->id, 'dispute_id' => $dispute->id]);
        $notifications->send($order->buyer, null, 'order_dispute_received', 'Khiếu nại đã được ghi nhận', 'Khiếu nại cho đơn #'.$order->id.' đang chờ quản trị viên xử lý.', '/me/orders', ['order_id' => $order->id, 'dispute_id' => $dispute->id]);
        return response()->json(['status' => 'success', 'message' => 'Đã mở khiếu nại; khoản tiền chờ giải ngân được tạm giữ.', 'data' => $this->resource($dispute)], 201);
    }

    public function acceptWalletRefund(Request $request, int $id): JsonResponse
    {
        $request->validate(['accept' => ['required', 'accepted']]);
        $dispute = DB::transaction(function () use ($request, $id): OrderDispute {
            $order = Order::lockForUpdate()->findOrFail($id);
            abort_unless($order->buyer_id === (int) $request->user()->id, 404);
            $dispute = $order->disputes()->where('status', 'open')->lockForUpdate()->firstOrFail();
            if ($order->payout_status === 'credited_to_wallet') throw new ConflictHttpException('Đơn đã giải ngân, không thể đổi phương án hoàn tiền.');
            $dispute->update(['buyer_wallet_refund_consent_at' => now()]);
            OrderDisputeEvent::create([
                'dispute_id' => $dispute->id, 'actor_id' => $request->user()->id,
                'action' => 'buyer_consented_wallet_refund',
                'note' => 'Người mua chủ động đồng ý nhận khoản hoàn về số dư H-Smart nếu quản trị viên chọn phương án này.',
            ]);
            return $dispute;
        }, 3);
        return response()->json(['status' => 'success', 'message' => 'Đã ghi nhận đồng ý nhận hoàn tiền vào số dư nếu phương án này được chấp thuận.', 'data' => $this->resource($dispute)]);
    }

    public function resource(OrderDispute $dispute): array
    {
        return [
            'id' => $dispute->id, 'order_id' => $dispute->order_id, 'opened_by' => $dispute->opened_by,
            'status' => $dispute->status, 'reason' => $dispute->reason, 'details' => $dispute->details,
            'evidence_notes' => $dispute->evidence_notes, 'resolution' => $dispute->resolution,
            'resolution_note' => $dispute->resolution_note,
            'buyer_wallet_refund_consent_at' => $dispute->buyer_wallet_refund_consent_at?->toIso8601String(),
            'created_at' => $dispute->created_at?->toIso8601String(), 'resolved_at' => $dispute->resolved_at?->toIso8601String(),
        ];
    }
}
