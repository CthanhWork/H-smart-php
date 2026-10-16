<?php

namespace App\Modules\Review\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Review\Models\Review;
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
        $reviews = Review::with('buyer:id,full_name')->where('product_id', $productId)->latest()->paginate(10);
        $average = Review::where('product_id', $productId)->avg('rating');
        return response()->json(['status' => 'success', 'message' => 'Đánh giá sản phẩm.', 'data' => [
            'content' => $reviews->getCollection()->map(fn (Review $review) => $this->resource($review))->all(),
            'total' => $reviews->total(),
            'average' => $average === null ? null : round((float) $average, 1),
        ]]);
    }

    public function store(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $review = DB::transaction(function () use ($request, $orderId, $data): Review {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            abort_unless($order->buyer_id === $request->user()->id, 404);
            if ($order->status !== Order::STATUS_COMPLETED) {
                throw new ConflictHttpException('Chỉ có thể đánh giá sau khi đơn hàng hoàn tất.');
            }
            if (Review::where('order_id', $order->id)->exists()) {
                throw ValidationException::withMessages(['order' => 'Đơn hàng này đã được đánh giá.']);
            }
            return Review::create([
                'order_id' => $order->id, 'product_id' => $order->product_id,
                'buyer_id' => $order->buyer_id, 'rating' => $data['rating'], 'comment' => trim($data['comment'] ?? '') ?: null,
            ])->load('buyer:id,full_name');
        });

        return response()->json(['status' => 'success', 'message' => 'Cảm ơn bạn đã đánh giá sản phẩm.', 'data' => $this->resource($review)], 201);
    }

    private function resource(Review $review): array
    {
        return ['id' => $review->id, 'order_id' => $review->order_id, 'rating' => $review->rating,
            'comment' => $review->comment, 'buyer' => ['id' => $review->buyer->id, 'full_name' => $review->buyer->full_name],
            'created_at' => $review->created_at->toIso8601String()];
    }
}
