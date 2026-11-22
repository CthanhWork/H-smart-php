<?php

namespace App\Modules\Product\Actions;

use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;

class HideProductAction
{
    public function execute(Product $product, string $reason): Product
    {
        // Approved listings awaiting their explicitly confirmed fee can also be hidden.
        if (!in_array($product->status, [Product::STATUS_PENDING_REVIEW, Product::STATUS_PENDING_LISTING_PAYMENT, Product::STATUS_ACTIVE])) {
            throw new \DomainException('Product cannot be hidden in current status');
        }

        return DB::transaction(function () use ($product, $reason) {
            $product->lockForUpdate()->find($product->id);
            $product->status = Product::STATUS_HIDDEN;
            $product->save();

            // TODO: Store hide_reason in admin_actions table when implemented

            return $product;
        });
    }
}
