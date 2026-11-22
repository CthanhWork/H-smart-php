<?php

namespace App\Modules\Product\Actions;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\ListingPublicationService;

class ApproveProductAction
{
    public function __construct(private readonly ListingPublicationService $publicationService) {}

    public function execute(Product $product): Product
    {
        if ($product->status !== Product::STATUS_PENDING_REVIEW) {
            throw new \DomainException('Product is not pending review');
        }

        return $this->publicationService->approve($product);
    }
}
