<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Actions\DeleteProductAction;
use App\Modules\Product\Actions\UpdateProductAction;
use App\Modules\Product\Http\Requests\ListProductsRequest;
use App\Modules\Product\Http\Requests\UpdateProductRequest;
use App\Modules\Product\Http\Resources\ProductResource;
use App\Modules\Product\Models\Product;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Services\ProductSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    public function index(ListProductsRequest $request): JsonResponse
    {
        $filters = (array) $request->input('filter', []);
        $filters['sort'] = $request->input('sort', $filters['sort'] ?? 'newest');
        return $this->page($request, app(ProductSearch::class)->query(
            $filters,
            $request->input('q'),
        ));
    }

    public function mine(ListProductsRequest $request): JsonResponse
    {
        $query = Product::query()->with('publication')->where('seller_id', $request->user()->id);
        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        return $this->page($request, $query);
    }

    private function page(ListProductsRequest $request, Builder $query): JsonResponse
    {
        $query->with(['images', 'category', 'seller:id,username,full_name', 'currentPromotion', 'nextPromotion'])
            ->addSelect([
                'seller_completed_sales_count' => Order::query()->selectRaw('COUNT(*)')
                    ->whereColumn('orders.seller_id', 'products.seller_id')
                    ->where('orders.status', Order::STATUS_COMPLETED),
                'seller_review_average' => Order::query()->selectRaw('AVG(review_rating)')
                    ->whereColumn('orders.seller_id', 'products.seller_id')
                    ->where('orders.status', Order::STATUS_COMPLETED)
                    ->whereNotNull('review_rating'),
                'seller_review_count' => Order::query()->selectRaw('COUNT(*)')
                    ->whereColumn('orders.seller_id', 'products.seller_id')
                    ->where('orders.status', Order::STATUS_COMPLETED)
                    ->whereNotNull('review_rating'),
            ]);
        if ($userId = $request->user()?->id) {
            $query->withExists(['favorites as is_favorite' => fn (Builder $favoriteQuery) => $favoriteQuery->where('user_id', $userId)]);
        }
        if ($sellerId = $request->input('filter.seller_id')) $query->where('seller_id', $sellerId);
        $number = (int) $request->input('page.number', 0);
        $size = (int) $request->input('page.size', 12);
        $results = $query;
        match ($request->input('sort', $request->input('filter.sort', 'newest'))) {
            'price_asc' => $results->orderBy('price')->orderByDesc('id'),
            'price_desc' => $results->orderByDesc('price')->orderByDesc('id'),
            default => $results->orderByDesc('created_at')->orderByDesc('id'),
        };
        $results = $results
            ->paginate($size, ['*'], 'page', $number + 1);
        $totalPages = $results->lastPage();

        return response()->json([
            'status' => 'success', 'message' => 'Đã tải danh sách sản phẩm.',
            'data' => [
                'content' => ProductResource::collection($results->items())->resolve(),
                'pageNo' => $number, 'pageSize' => $size,
                'totalElements' => $results->total(), 'totalPages' => $totalPages,
                'last' => $number + 1 >= $totalPages,
            ],
            'meta' => ['pagination' => ['page' => $number, 'size' => $size, 'total' => $results->total(), 'totalPages' => $totalPages]],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $productQuery = Product::with(['images', 'category', 'seller:id,username,full_name', 'publication', 'currentPromotion', 'nextPromotion'])
            ->withAvg('reviews as reviews_avg_rating', 'review_rating')
            ->withCount('reviews')
            ->addSelect([
                'seller_completed_sales_count' => Order::query()->selectRaw('COUNT(*)')
                    ->whereColumn('orders.seller_id', 'products.seller_id')
                    ->where('orders.status', Order::STATUS_COMPLETED),
                'seller_review_average' => Order::query()->selectRaw('AVG(review_rating)')
                    ->whereColumn('orders.seller_id', 'products.seller_id')
                    ->where('orders.status', Order::STATUS_COMPLETED)
                    ->whereNotNull('review_rating'),
                'seller_review_count' => Order::query()->selectRaw('COUNT(*)')
                    ->whereColumn('orders.seller_id', 'products.seller_id')
                    ->where('orders.status', Order::STATUS_COMPLETED)
                    ->whereNotNull('review_rating'),
            ]);
        if ($userId = $request->user()?->id) {
            $productQuery->withExists(['favorites as is_favorite' => fn (Builder $favoriteQuery) => $favoriteQuery->where('user_id', $userId)]);
        }
        $product = $productQuery->findOrFail($id);
        $user = $request->user();
        abort_unless($product->status === Product::STATUS_ACTIVE || ($user && $product->seller_id === $user->id), 404);

        return response()->json(['status' => 'success', 'message' => 'Đã tải sản phẩm.', 'data' => new ProductResource($product)]);
    }

    public function update(UpdateProductRequest $request, int $id, UpdateProductAction $action): JsonResponse
    {
        $product = $action->execute($id, (int) $request->user()->id, $request->validated(), $request->file('images', []));

        return response()->json(['status' => 'success', 'message' => 'Đã cập nhật sản phẩm và gửi duyệt lại.', 'data' => new ProductResource($product)]);
    }

    public function destroy(Request $request, int $id, DeleteProductAction $action): JsonResponse
    {
        $action->execute($id, (int) $request->user()->id);

        return response()->json(['status' => 'success', 'message' => 'Đã xóa sản phẩm.', 'data' => null]);
    }
}
