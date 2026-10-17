<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Product\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use App\Modules\Admin\Services\AdminAuditLogger;

class AdminCategoryController
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Danh sách danh mục.', 'data' => Category::withCount('products')->orderBy('name')->get()]);
    }

    public function store(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:150', 'unique:categories,name']]);
        $slug = $this->uniqueSlug($data['name']);
        $category = Category::create(['name' => trim($data['name']), 'slug' => $slug]);
        $audit->record($request->user(), 'category_created', 'category', $category->id, 'Tạo danh mục '.$category->name, ['name' => $category->name]);

        return response()->json(['success' => true, 'message' => 'Đã thêm danh mục.', 'data' => $category->loadCount('products')], 201);
    }

    public function update(Request $request, int $id, AdminAuditLogger $audit): JsonResponse
    {
        $category = Category::findOrFail($id);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:150', Rule::unique('categories', 'name')->ignore($category->id)]]);
        $oldName = $category->name;
        $category->update(['name' => trim($data['name']), 'slug' => $this->uniqueSlug($data['name'], $category->id)]);
        $audit->record($request->user(), 'category_updated', 'category', $category->id, 'Sửa danh mục '.$oldName.' thành '.$category->name, ['before' => $oldName, 'after' => $category->name]);

        return response()->json(['success' => true, 'message' => 'Đã cập nhật danh mục.', 'data' => $category->loadCount('products')]);
    }

    public function destroy(Request $request, int $id, AdminAuditLogger $audit): JsonResponse
    {
        $category = Category::findOrFail($id);
        if ($category->products()->exists()) {
            throw new ConflictHttpException('Danh mục đang được sản phẩm sử dụng nên chưa thể xóa.');
        }
        $name = $category->name;
        $category->delete();
        $audit->record($request->user(), 'category_deleted', 'category', $id, 'Xóa danh mục '.$name, ['name' => $name]);

        return response()->json(['success' => true, 'message' => 'Đã xóa danh mục.', 'data' => null]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (Category::where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
