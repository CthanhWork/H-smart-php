<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Product\Actions\ApproveProductAction;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;

class AdminProductApproveController extends Controller
{
    public function __construct(
        private readonly ApproveProductAction $approveProductAction
    ) {}

    public function __invoke(int $productId): JsonResponse
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Product not found',
                'data' => null,
            ], 404);
        }

        try {
            $product = $this->approveProductAction->execute($product);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product approved successfully',
                'data' => ['product' => $product],
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'status' => 409,
                'message' => $e->getMessage(),
                'data' => null,
            ], 409);
        }
    }
}
