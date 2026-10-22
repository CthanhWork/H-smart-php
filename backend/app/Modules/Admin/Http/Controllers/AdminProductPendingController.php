<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductPendingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 100);
        $page = (int) $request->input('page', 1);

        $products = Product::with(['images', 'category'])->where('status', Product::STATUS_PENDING_REVIEW)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Pending products retrieved successfully',
            'data' => [
                'data' => $products->items(),
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }
}
