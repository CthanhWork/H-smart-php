<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Actions\CreateProductAction;
use App\Modules\Product\Http\Requests\CreateProductRequest;
use App\Modules\Product\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;

class CreateProductController
{
    public function __invoke(CreateProductRequest $request, CreateProductAction $create): JsonResponse
    {
        $data = $request->validated();
        $product = $create->execute(
            (int) $request->user()->id,
            $data['title'],
            $data['description'] ?? null,
            (int) $data['price'],
            (int) $data['category_id'],
            $data['condition'],
            (bool) ($data['negotiable'] ?? false),
            isset($data['min_price']) ? (int) $data['min_price'] : null,
            $request->file('images', []),
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi sản phẩm để duyệt.',
            'data' => new ProductResource($product),
        ], 201);
    }
}
