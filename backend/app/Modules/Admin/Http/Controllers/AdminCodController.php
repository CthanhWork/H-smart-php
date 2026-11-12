<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminCodController extends Controller
{
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:delivered,carrier_settled,seller_paid,delivery_failed,retry_delivery,returned'],
            'reason' => ['required_if:action,delivery_failed', 'nullable', 'string', 'min:3', 'max:500'],
        ]);

        $order = DB::transaction(function () use ($id, $data): Order {
            $order = Order::lockForUpdate()->findOrFail($id);
            if ($order->payment_provider !== 'cod') {
                throw new ConflictHttpException('Thao tác này chỉ áp dụng cho đơn COD.');
            }

            $now = now();
            if ($data['action'] === 'delivered') {
                if ($order->status !== Order::STATUS_SHIPPING || $order->cod_collected_at) throw new ConflictHttpException('Đơn COD chưa ở trạng thái có thể xác nhận giao và thu tiền.');
                $order->update(['status' => Order::STATUS_COMPLETED, 'completed_at' => $now, 'cod_collected_at' => $now, 'delivery_status' => 'delivered', 'delivery_failed_reason' => null, 'payment_status' => 'paid', 'payment_paid_at' => $now]);
                Product::whereKey($order->product_id)->lockForUpdate()->update(['status' => Product::STATUS_SOLD]);
            } elseif ($data['action'] === 'carrier_settled') {
                if (! $order->cod_collected_at || $order->carrier_settled_at) throw new ConflictHttpException('Đơn chưa thu COD hoặc đã được đối soát.');
                $order->update(['carrier_settled_at' => $now]);
            } elseif ($data['action'] === 'seller_paid') {
                if (! $order->carrier_settled_at || $order->seller_paid_at) throw new ConflictHttpException('Tiền COD chưa đối soát hoặc đã ghi nhận chi trả.');
                $order->update(['seller_paid_at' => $now]);
            } elseif ($data['action'] === 'delivery_failed') {
                if ($order->status !== Order::STATUS_SHIPPING || $order->cod_collected_at) throw new ConflictHttpException('Đơn không ở trạng thái đang giao.');
                $order->update(['delivery_status' => 'failed', 'delivery_failed_reason' => $data['reason']]);
            } elseif ($data['action'] === 'retry_delivery') {
                if ($order->delivery_status !== 'failed' || $order->status !== Order::STATUS_SHIPPING) throw new ConflictHttpException('Chỉ có thể giao lại đơn đã ghi nhận giao thất bại.');
                $order->update(['delivery_status' => 'in_transit', 'delivery_failed_reason' => null]);
            } else {
                if ($order->status !== Order::STATUS_SHIPPING || $order->delivery_status !== 'failed') throw new ConflictHttpException('Chỉ ghi nhận hoàn hàng sau khi giao thất bại.');
                $order->update(['delivery_status' => 'returned_to_seller', 'returned_at' => $now]);
            }

            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'statusHistory']);
        });

        return response()->json(['status' => 'success', 'message' => 'Đã cập nhật đối soát COD.', 'data' => new OrderResource($order)]);
    }
}
