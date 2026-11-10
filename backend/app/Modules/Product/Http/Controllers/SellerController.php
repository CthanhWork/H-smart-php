<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;

class SellerController
{
    public function show(int $id): JsonResponse
    {
        $seller = User::query()->whereKey($id)->where('status', 'active')->firstOrFail();
        $completedOrders = Order::query()
            ->where('seller_id', $seller->id)
            ->where('status', Order::STATUS_COMPLETED);
        $reviewedOrders = (clone $completedOrders)->whereNotNull('review_rating');

        return response()->json([
            'status' => 'success',
            'message' => 'Đã tải hồ sơ người bán.',
            'data' => [
                'id' => $seller->id,
                'name' => $seller->full_name ?: $seller->username,
                'joined_at' => $seller->created_at?->toIso8601String(),
                'completed_sales_count' => (int) $completedOrders->count(),
                'review_average' => ($average = $reviewedOrders->avg('review_rating')) === null ? null : round((float) $average, 1),
                'review_count' => (int) $reviewedOrders->count(),
                'active_products_count' => (int) Product::query()
                    ->where('seller_id', $seller->id)
                    ->where('status', Product::STATUS_ACTIVE)
                    ->count(),
            ],
        ]);
    }
}
