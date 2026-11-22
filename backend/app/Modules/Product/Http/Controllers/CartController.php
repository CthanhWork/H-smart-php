<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Http\Resources\ProductResource;
use App\Modules\Product\Models\CartItem;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CartController
{
    public function index(Request $request): JsonResponse
    {
        $items = CartItem::query()->where('user_id', $request->user()->id)
            ->with(['product.images', 'product.category', 'product.seller:id,username,full_name'])->orderBy('id')->get();
        $items = $items->map(fn (CartItem $item) => [
            'id' => $item->id,
            'available' => $item->product->status === Product::STATUS_ACTIVE && $item->product->seller_id !== $request->user()->id,
            'product' => ProductResource::make($item->product)->resolve(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Giỏ hàng của bạn.', 'data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);
        $product = Product::findOrFail($data['product_id']);
        if ($product->seller_id === (int) $request->user()->id) {
            throw new ConflictHttpException('Bạn không thể mua sản phẩm do chính mình đăng.');
        }
        if ($product->status !== Product::STATUS_ACTIVE) {
            throw new ConflictHttpException('Sản phẩm hiện không còn khả dụng.');
        }
        $inserted = DB::table('cart_items')->insertOrIgnore([
            'user_id' => $request->user()->id, 'product_id' => $product->id,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]);
        $item = CartItem::query()->where('user_id', $request->user()->id)->where('product_id', $product->id)->firstOrFail();

        return response()->json(['status' => 'success', 'message' => $inserted ? 'Đã thêm vào giỏ hàng.' : 'Sản phẩm đã có trong giỏ hàng.', 'data' => ['id' => $item->id]], $inserted ? 201 : 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        CartItem::query()->where('user_id', $request->user()->id)->whereKey($id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Đã xóa sản phẩm khỏi giỏ hàng.', 'data' => null]);
    }
}
