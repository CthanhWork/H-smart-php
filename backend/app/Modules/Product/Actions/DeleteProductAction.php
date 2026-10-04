<?php

namespace App\Modules\Product\Actions;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\ProductImageStorage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteProductAction
{
    public function __construct(private ProductImageStorage $imageStorage) {}

    public function execute(int $id, int $sellerId): void
    {
        DB::transaction(function () use ($id, $sellerId): void {
            $product = Product::with('images')->lockForUpdate()->findOrFail($id);
            abort_unless($product->seller_id === $sellerId, 404);
            if (in_array($product->status, [Product::STATUS_RESERVED, Product::STATUS_SOLD], true)) {
                throw new HttpException(409, 'Không thể xóa sản phẩm đã đặt giữ hoặc đã bán.');
            }
            $urls = $product->images->pluck('url')->all();
            $product->delete();
            DB::afterCommit(fn () => $this->imageStorage->deleteUrls($urls));
        });
    }
}
