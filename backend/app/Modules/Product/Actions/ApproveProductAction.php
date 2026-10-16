<?php

namespace App\Modules\Product\Actions;

use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;

class ApproveProductAction
{
    public function execute(Product $product): Product
    {
        if ($product->status !== Product::STATUS_PENDING_REVIEW) {
            throw new \DomainException('Product is not pending review');
        }

        return DB::transaction(function () use ($product) {
            $product->lockForUpdate()->find($product->id);
            $product->status = Product::STATUS_ACTIVE;
            $product->save();
            return $product;
        });
    }
}
