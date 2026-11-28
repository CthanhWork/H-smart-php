<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Danh sách danh mục.',
            'data' => Category::query()->where('slug', 'not like', 'qa-%')->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
