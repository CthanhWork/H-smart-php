<?php

namespace App\Modules\Product\Services;

use App\Modules\Admin\Services\MarketplaceSettings;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductPromotion;
use Illuminate\Database\Eloquent\Builder;

class ProductSearch
{
    public function query(array $filters = [], ?string $keyword = null): Builder
    {
        $query = Product::query()->where('status', Product::STATUS_ACTIVE)
            ->with(['images', 'category', 'seller:id,username,full_name', 'currentPromotion', 'nextPromotion']);

        if ($keyword = trim((string) $keyword)) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($keyword));
            $like = '%'.$escaped.'%';
            $query->where(function (Builder $search) use ($like): void {
                $search->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereHas('category', fn (Builder $category) => $category->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like]));
            });
        }

        if (in_array($filters['condition'] ?? null, Product::CONDITIONS, true)) {
            $query->where('condition', $filters['condition']);
        }
        if (isset($filters['category_id']) && is_numeric($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query->where('price', '>=', (int) $filters['min_price']);
        }
        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->where('price', '<=', (int) $filters['max_price']);
        }

        $sort = $filters['sort'] ?? 'newest';
        if ($sort === 'price_asc') {
            return $query->orderBy('price')->orderByDesc('id');
        }
        if ($sort === 'price_desc') {
            return $query->orderByDesc('price')->orderByDesc('id');
        }

        $promotionRank = ProductPromotion::query()->selectRaw("CASE WHEN package_type = 'vip' THEN 2 ELSE 1 END")
            ->whereColumn('product_promotions.product_id', 'products.id')
            ->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())
            ->where(function (Builder $promotion) use ($filters): void {
                if (isset($filters['category_id'])) {
                    $promotion->whereIn('package_type', ['vip', 'boost']);
                } else {
                    $promotion->where('package_type', 'vip');
                }
            })->orderByRaw("CASE WHEN package_type = 'vip' THEN 2 ELSE 1 END DESC")->limit(1);
        $promotionExists = ProductPromotion::query()->select('id')
            ->whereColumn('product_promotions.product_id', 'products.id')
            ->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())
            ->where(function (Builder $promotion) use ($filters): void {
                if (isset($filters['category_id'])) {
                    $promotion->whereIn('package_type', ['vip', 'boost']);
                } else {
                    $promotion->where('package_type', 'vip');
                }
            });
        $maxId = max(1, (int) (Product::max('id') ?? 1));
        $rotationPivot = (intdiv(now()->timestamp, 300) % $maxId) + 1;
        $slotLimit = max(0, app(MarketplaceSettings::class)->get('promotion_slots'));
        if ($slotLimit === 0) {
            return $query->orderByDesc('created_at')->orderByDesc('id');
        }
        $topPromoted = (clone $query)->select('products.id')->whereExists($promotionExists)
            ->orderByDesc($promotionRank)
            ->orderByRaw('CASE WHEN products.id >= ? THEN 0 ELSE 1 END', [$rotationPivot])
            ->orderBy('products.id')->limit($slotLimit);
        $topPromoted->select('products.id as promoted_product_id');

        return $query->leftJoinSub($topPromoted, 'promotion_slots', function ($join): void {
            $join->on('promotion_slots.promoted_product_id', '=', 'products.id');
        })->orderByDesc($promotionRank)
            ->orderByRaw('CASE WHEN promotion_slots.promoted_product_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('products.created_at')->orderByDesc('products.id');
    }
}
