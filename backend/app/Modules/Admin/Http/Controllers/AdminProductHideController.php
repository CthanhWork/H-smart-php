<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Http\Requests\AdminProductHideRequest;
use App\Modules\Product\Actions\HideProductAction;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;

class AdminProductHideController extends Controller
{
    public function __construct(
        private readonly HideProductAction $hideProductAction
    ) {}

    public function __invoke(AdminProductHideRequest $request, int $productId): JsonResponse
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
            $product = $this->hideProductAction->execute($product, $request->validated('reason'));

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product hidden successfully',
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
