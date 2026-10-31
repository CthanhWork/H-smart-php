<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Http\Resources\ProductResource;
use App\Modules\Product\Models\Favorite;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FavoriteController
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $pageNumber = max(0, (int) $request->input('page[number]', 0));
        $pageSize = max(1, min(100, (int) $request->input('page[size]', 12)));
        $favorites = Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->whereHas('favorites', fn ($query) => $query->where('user_id', $userId))
            ->with(['images', 'category'])
            ->withAvg('reviews as reviews_avg_rating', 'review_rating')
            ->withCount('reviews')
            ->select('products.*')
            ->withExists(['favorites as is_favorite' => fn ($query) => $query->where('user_id', $userId)])
            ->orderByDesc(Favorite::query()->select('created_at')->whereColumn('product_id', 'products.id')->where('user_id', $userId)->limit(1))
            ->paginate($pageSize, ['products.*'], 'page', $pageNumber + 1);

        return response()->json([
            'status' => 'success',
            'message' => 'Danh sách sản phẩm yêu thích.',
            'data' => [
                'content' => ProductResource::collection($favorites->items())->resolve(),
                'pageNo' => $pageNumber,
                'pageSize' => $pageSize,
                'totalElements' => $favorites->total(),
                'totalPages' => $favorites->lastPage(),
                'last' => $pageNumber + 1 >= $favorites->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);
        $product = Product::findOrFail($data['product_id']);
        if ($product->status !== Product::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['product_id' => 'Chỉ có thể lưu sản phẩm đang bán.']);
        }

        Favorite::firstOrCreate(['user_id' => $request->user()->id, 'product_id' => $product->id]);

        return response()->json(['status' => 'success', 'message' => 'Đã thêm vào danh sách yêu thích.', 'data' => null], 201);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        Favorite::where('user_id', $request->user()->id)->where('product_id', $productId)->delete();

        return response()->json(['status' => 'success', 'message' => 'Đã xóa khỏi danh sách yêu thích.', 'data' => null]);
    }
}
