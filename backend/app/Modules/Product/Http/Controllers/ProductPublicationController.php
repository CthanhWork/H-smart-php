<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Http\Resources\ProductResource;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\ListingPublicationService;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductPublicationController
{
    public function quota(Request $request, ListingPublicationService $service): JsonResponse
    {
        return response()->json(['status' => 'success', 'message' => 'Hạn mức tin đăng tháng này.', 'data' => $service->quote((int) $request->user()->id)]);
    }

    public function quote(Request $request, int $id, ListingPublicationService $service): JsonResponse
    {
        $product = Product::where('seller_id', $request->user()->id)->with('publication')->findOrFail($id);
        abort_unless($product->status === Product::STATUS_PENDING_LISTING_PAYMENT, 409, 'Tin chưa được duyệt để xuất bản.');
        return response()->json(['status' => 'success', 'message' => 'Báo giá xuất bản tin.', 'data' => [
            ...$service->quote((int) $request->user()->id), 'product_id' => $product->id,
            'quoted_fee' => $product->publication?->fee_amount,
        ]]);
    }

    public function pay(Request $request, int $id, ListingPublicationService $service, WalletLedger $ledger): JsonResponse
    {
        $data = $request->validate(['expected_fee' => ['required', 'integer', 'min:0', 'max:1000000']]);
        $product = $service->pay((int) $request->user()->id, $id, (int) $data['expected_fee'], $ledger);
        return response()->json(['status' => 'success', 'message' => 'Đã thanh toán phí đăng tin từ số dư mô phỏng và xuất bản sản phẩm.', 'data' => new ProductResource($product)]);
    }
}
