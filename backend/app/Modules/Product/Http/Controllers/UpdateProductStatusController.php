<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateProductStatusController
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in([Product::STATUS_DRAFT, Product::STATUS_PENDING_REVIEW])]]);
        $product = DB::transaction(function () use ($request, $id, $data): Product {
            $product = Product::with('images')->lockForUpdate()->findOrFail($id);
            abort_unless($product->seller_id === $request->user()->id, 404);

            $allowed = ($product->status === Product::STATUS_ACTIVE && $data['status'] === Product::STATUS_DRAFT)
                || ($product->status === Product::STATUS_DRAFT && $data['status'] === Product::STATUS_PENDING_REVIEW);
            if (! $allowed) {
                throw ValidationException::withMessages(['status' => 'Chỉ có thể tạm ẩn tin đang bán hoặc gửi duyệt lại tin nháp.']);
            }
            if ($data['status'] === Product::STATUS_PENDING_REVIEW && $product->images()->count() === 0) {
                throw ValidationException::withMessages(['status' => 'Thêm ít nhất một ảnh trước khi gửi duyệt lại.']);
            }

            $product->update(['status' => $data['status']]);
            return $product->refresh()->load(['images', 'category']);
        });

        return response()->json(['status' => 'success', 'message' => $product->status === Product::STATUS_DRAFT ? 'Tin đã được tạm ẩn.' : 'Tin đã được gửi quản trị viên duyệt lại.', 'data' => new \App\Modules\Product\Http\Resources\ProductResource($product)]);
    }
}
