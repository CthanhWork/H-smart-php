<?php

namespace App\Modules\Offer\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateOfferAction
{
    public function execute(int $buyerId, int $productId, int $offerPrice): Offer
    {
        return DB::transaction(function () use ($buyerId, $productId, $offerPrice): Offer {
            $product = Product::lockForUpdate()->findOrFail($productId);

            // Validate product is available for offers
            if ($product->status !== Product::STATUS_ACTIVE) {
                throw new InvalidArgumentException('Sản phẩm hiện không nhận đề nghị mua.');
            }

            if (! $product->negotiable) {
                throw new InvalidArgumentException('Sản phẩm này không hỗ trợ trả giá.');
            }

            if ($offerPrice > $product->price) {
                throw new InvalidArgumentException('Giá đề nghị không được cao hơn giá bán.');
            }

            if ($product->min_price !== null && $offerPrice < $product->min_price) {
                throw new InvalidArgumentException('Giá đề nghị thấp hơn mức tối thiểu người bán chấp nhận.');
            }

            // Validate buyer is not the seller
            if ($product->seller_id === $buyerId) {
                throw new InvalidArgumentException('Bạn không thể gửi đề nghị cho sản phẩm của mình.');
            }

            // Check for existing pending offer (will be caught by unique constraint anyway)
            $existingOffer = Offer::where('buyer_id', $buyerId)
                ->where('product_id', $productId)
                ->where('status', Offer::STATUS_PENDING)
                ->exists();

            if ($existingOffer) {
                throw new InvalidArgumentException('Bạn đã có một đề nghị đang chờ phản hồi cho sản phẩm này.');
            }

            // Create offer with 24h expiry
            $offer = Offer::create([
                'product_id' => $productId,
                'buyer_id' => $buyerId,
                'seller_id' => $product->seller_id,
                'offer_price' => $offerPrice,
                'status' => Offer::STATUS_PENDING,
                'expires_at' => now()->addHours(24),
            ]);

            return $offer->load(['product.images', 'buyer', 'seller']);
        });
    }
}
