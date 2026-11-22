<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Product\Models\ProductPromotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPromotionController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'in:active,scheduled,expired,all']]);
        $query = ProductPromotion::with(['product:id,title,status', 'seller:id,username,full_name,email'])->latest('id');
        if (($data['status'] ?? 'all') === 'active') $query->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now());
        if (($data['status'] ?? 'all') === 'scheduled') $query->where('status', 'active')->where('starts_at', '>', now())->where('expires_at', '>', now());
        if (($data['status'] ?? 'all') === 'expired') $query->where(fn ($builder) => $builder->where('status', 'expired')->orWhere('expires_at', '<=', now()));
        $promotions = $query->paginate(min((int) $request->input('per_page', 30), 100));

        return response()->json(['success' => true, 'message' => 'Gói ưu tiên đã mua.', 'data' => [
            'data' => $promotions->getCollection()->map(fn (ProductPromotion $promotion) => [
                'id' => $promotion->id, 'package_type' => $promotion->package_type, 'fee_amount' => $promotion->fee_amount,
                'status' => $promotion->status, 'reference' => $promotion->reference,
                'starts_at' => $promotion->starts_at?->toIso8601String(), 'expires_at' => $promotion->expires_at?->toIso8601String(),
                'product' => $promotion->product?->only(['id', 'title', 'status']),
                'seller' => $promotion->seller?->only(['id', 'username', 'full_name', 'email']),
            ])->values(),
            'current_page' => $promotions->currentPage(), 'last_page' => $promotions->lastPage(), 'total' => $promotions->total(),
        ]]);
    }
}
