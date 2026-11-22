<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\VietQrPaymentAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class VietQrController
{
    public function create(Request $request, int $id, VietQrPaymentAdapter $adapter): JsonResponse
    {
        $instruction = DB::transaction(function () use ($request, $id, $adapter): array {
            $order = Order::where('buyer_id', $request->user()->id)->lockForUpdate()->findOrFail($id);
            if ($order->status !== Order::STATUS_PENDING_PAYMENT || $order->payment_status !== 'pending' || $order->expires_at->isPast()) {
                throw new ConflictHttpException('Đơn hàng không còn chờ nhận thanh toán.');
            }
            return $adapter->instruction($order);
        });

        return response()->json(['status' => 'success', 'message' => 'Đã tạo hướng dẫn VietQR. Đơn vẫn chờ xác minh sao kê; ảnh QR không xác nhận thanh toán.', 'data' => $instruction]);
    }
}
