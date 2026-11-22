<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Http\Resources\ProductResource;
use App\Modules\Product\Services\ProductPromotionService;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPromotionController
{
    public function packages(ProductPromotionService $service): JsonResponse
    {
        return response()->json(['status' => 'success', 'message' => 'Gói ưu tiên tin đăng.', 'data' => $service->packages()]);
    }

    public function purchase(Request $request, int $id, ProductPromotionService $service, WalletLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'package_type' => ['required', 'in:boost,vip'],
            'expected_fee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:80', 'alpha_dash'],
        ]);
        $result = $service->purchase((int) $request->user()->id, $id, $data['package_type'], (int) $data['expected_fee'], $data['idempotency_key'], $ledger);
        $promotion = $result['promotion'];
        return response()->json(['status' => 'success', 'message' => 'Đã thanh toán gói ưu tiên từ số dư mô phỏng.', 'data' => [
            'product' => new ProductResource($result['product']),
            'purchase' => ['package_type' => $promotion->package_type, 'starts_at' => $promotion->starts_at?->toIso8601String(), 'expires_at' => $promotion->expires_at?->toIso8601String()],
        ]]);
    }
}
