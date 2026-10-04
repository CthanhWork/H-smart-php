<?php

namespace App\Modules\Product\Actions;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\ProductImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateProductAction
{
    public function __construct(private ProductImageStorage $imageStorage) {}

    /** @param UploadedFile[] $images */
    public function execute(
        int $sellerId,
        string $title,
        ?string $description,
        int $price,
        string $condition,
        array $images,
    ): Product {
        $stored = [];

        try {
            return DB::transaction(function () use ($sellerId, $title, $description, $price, $condition, $images, &$stored): Product {
                $product = Product::create([
                    'seller_id' => $sellerId,
                    'title' => $title,
                    'description' => $description,
                    'price' => $price,
                    'condition' => $condition,
                    'status' => Product::STATUS_PENDING_REVIEW,
                ]);

                foreach ($images as $order => $image) {
                    $media = $this->imageStorage->store($image);
                    $stored[] = $media['path'];
                    $product->images()->create([
                        'url' => $media['url'],
                        'sort_order' => $order,
                    ]);
                }

                return $product->load('images');
            });
        } catch (Throwable $error) {
            $this->imageStorage->deletePaths($stored);
            throw $error;
        }
    }
}
