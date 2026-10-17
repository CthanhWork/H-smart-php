<?php

namespace App\Modules\Review\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReviewController
{
    public function index(Request $request, int $productId): JsonResponse
    {
        Product::findOrFail($productId);
        $reviews = Order::with('buyer:id,full_name')->where('product_id', $productId)->whereNotNull('review_rating')->latest('review_created_at')->paginate(10);
        $average = Order::where('product_id', $productId)->whereNotNull('review_rating')->avg('review_rating');
        return response()->json(['status' => 'success', 'message' => 'Đánh giá sản phẩm.', 'data' => [
            'content' => $reviews->getCollection()->map(fn (Order $review) => $this->resource($review))->all(),
            'total' => $reviews->total(),
            'average' => $average === null ? null : round((float) $average, 1),
        ]]);
    }

    public function store(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $review = DB::transaction(function () use ($request, $orderId, $data): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            abort_unless($order->buyer_id === $request->user()->id, 404);
            if ($order->status !== Order::STATUS_COMPLETED) {
                throw new ConflictHttpException('Chỉ có thể đánh giá sau khi đơn hàng hoàn tất.');
            }
            if ($order->review_rating !== null) {
                throw ValidationException::withMessages(['order' => 'Đơn hàng này đã được đánh giá.']);
            }
            $order->update([
                'review_rating' => $data['rating'],
                'review_comment' => trim($data['comment'] ?? '') ?: null,
                'review_created_at' => now(),
                'review_updated_at' => now(),
            ]);
            return $order->load('buyer:id,full_name');
        });

        return response()->json(['status' => 'success', 'message' => 'Cảm ơn bạn đã đánh giá sản phẩm.', 'data' => $this->resource($review)], 201);
    }

    private function resource(Order $review): array
    {
        return ['id' => $review->id, 'order_id' => $review->id, 'rating' => $review->review_rating,
            'comment' => $review->review_comment, 'buyer' => ['id' => $review->buyer->id, 'full_name' => $review->buyer->full_name],
            'created_at' => $review->review_created_at?->toIso8601String()];
    }
}
