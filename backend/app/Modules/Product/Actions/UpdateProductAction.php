<?php

namespace App\Modules\Product\Actions;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\ProductImageStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class UpdateProductAction
{
    public function __construct(private ProductImageStorage $imageStorage) {}

    public function execute(int $id, int $sellerId, array $data, array $uploads): Product
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($id, $sellerId, $data, $uploads, &$stored): Product {
                $product = Product::with('images')->lockForUpdate()->findOrFail($id);
                abort_unless($product->seller_id === $sellerId, 404);
                if (in_array($product->status, [Product::STATUS_RESERVED, Product::STATUS_SOLD], true)) {
                    throw new HttpException(409, 'Không thể chỉnh sửa sản phẩm đã đặt giữ hoặc đã bán.');
                }

                $current = $product->images->keyBy('id');
                $removed = $data['remove_image_ids'] ?? [];
                $order = $data['image_order'] ?? null;
                foreach ($removed as $imageId) {
                    if (! $current->has($imageId)) {
                        throw ValidationException::withMessages(['remove_image_ids' => 'Ảnh cần xóa không thuộc sản phẩm.']);
                    }
                }
                $retained = $current->reject(fn ($image) => in_array($image->id, $removed));
                $retainedIds = $retained->pluck('id')->map(fn ($id) => (int) $id)->all();
                if ($order !== null && (count($order) !== count($retainedIds) || array_diff($order, $retainedIds) !== [] || array_diff($retainedIds, $order) !== [])) {
                    throw ValidationException::withMessages(['image_order' => 'Thứ tự ảnh phải gồm đầy đủ các ảnh được giữ lại.']);
                }
                if ($retained->count() + count($uploads) > 5) {
                    throw ValidationException::withMessages(['images' => 'Tối đa 5 ảnh cho một sản phẩm.']);
                }

                $oldUrls = $current->only($removed)->pluck('url')->all();
                $product->images()->whereIn('id', $removed)->delete();
                $orderedIds = $order ?? $retained->keys()->all();
                foreach ($orderedIds as $index => $imageId) {
                    $retained[$imageId]->update(['sort_order' => $index]);
                }
                foreach ($uploads as $index => $upload) {
                    $media = $this->imageStorage->store($upload);
                    $stored[] = $media['path'];
                    $product->images()->create(['url' => $media['url'], 'sort_order' => count($orderedIds) + $index]);
                }
                $product->update([
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'condition' => $data['condition'],
                    'status' => Product::STATUS_PENDING_REVIEW,
                ]);
                DB::afterCommit(fn () => $this->imageStorage->deleteUrls($oldUrls));

                return $product->refresh()->load('images');
            });
        } catch (Throwable $error) {
            $this->imageStorage->deletePaths($stored);
            throw $error;
        }
    }
}
