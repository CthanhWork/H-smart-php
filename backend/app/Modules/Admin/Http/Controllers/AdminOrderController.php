<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Models\Order;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminOrderController extends Controller
{
    public function release(Request $request, int $id, WalletLedger $ledger, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1000']]);
        $order = DB::transaction(function () use ($request, $id, $ledger, $audit, $data): Order {
            $order = Order::lockForUpdate()->findOrFail($id);
            if ($order->payment_status !== 'paid' || $order->payout_status === 'credited_to_wallet' || $order->seller_paid_at) throw new ConflictHttpException('Đơn chưa đủ điều kiện hoặc đã giải ngân.');
            if (! $order->release_eligible_at || $order->release_eligible_at->isFuture()) throw new ConflictHttpException('Đơn chưa hết thời hạn chờ 48 giờ.');
            if ($order->disputes()->whereIn('status', ['open', 'reconciliation_pending'])->exists() || $order->payout_status === 'held') throw new ConflictHttpException('Đơn đang có khiếu nại hoặc lệnh giữ tiền.');
            if ($order->payment_method === 'cod' && $order->cod_remaining_collection_type !== 'direct' && ! $order->carrier_settled_at) throw new ConflictHttpException('Đơn COD chưa được đối soát.');
            $ledger->releaseOrderToWallet($order);
            $now = now();
            $order->update(['payout_status' => 'credited_to_wallet', 'seller_paid_at' => $now, 'wallet_credited_at' => $now]);
            $audit->record($request->user(), 'order_funds_released_to_wallet', 'order', $order->id, 'Admin xác nhận giải ngân đơn hàng vào ví.', ['amount' => $order->payment_method === 'cod' && $order->cod_deposit_amount > 0 ? (int) $order->cod_deposit_amount - (int) $order->platform_fee : (int) $order->product_amount - (int) $order->platform_fee, 'note' => $data['note']]);
            return $order;
        }, 3);
        return response()->json(['status' => 'success', 'message' => 'Đã giải ngân vào ví người bán.', 'data' => ['order_id' => $order->id, 'payout_status' => $order->payout_status, 'wallet_credited_at' => $order->wallet_credited_at?->toIso8601String()]]);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'in:pending_payment,paid,processing,shipping,completed,cancelled'], 'q' => ['nullable', 'string', 'max:120']]);
        $query = Order::with(['product:id,title,description,price,condition,category_id', 'product.images', 'buyer:id,email,full_name', 'seller:id,email,full_name', 'statusHistory'])->latest();
        if (!empty($data['status'])) $query->where('status', $data['status']);
        if (!empty($data['q'])) {
            $term = trim($data['q']);
            $query->where(function ($builder) use ($term): void {
                if (ctype_digit($term)) $builder->where('id', (int) $term);
                $builder->orWhere('tracking_number', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($q) => $q->where('title', 'like', "%{$term}%"))
                    ->orWhereHas('buyer', fn ($q) => $q->where('email', 'like', "%{$term}%")->orWhere('full_name', 'like', "%{$term}%"))
                    ->orWhereHas('seller', fn ($q) => $q->where('email', 'like', "%{$term}%")->orWhere('full_name', 'like', "%{$term}%"));
            });
        }
        return response()->json(['success' => true, 'message' => 'Danh sách giao dịch.', 'data' => $query->paginate(min((int) $request->input('per_page', 20), 100))]);
    }
}
