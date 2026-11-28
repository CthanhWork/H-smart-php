<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('title', 'like', 'DEEPAUDIT-%')
                    ->orWhere('title', 'like', 'QA %')
                    ->orWhere('title', 'like', 'QA-%')
                    ->orWhere('title', 'like', 'Kiểm thử đăng bán Codex QA%')
                    ->orWhere('title', 'like', 'Sản phẩm kiểm thử%')
                    ->orWhere('title', 'like', 'Tin thử nhiều ảnh - kiểm tra gallery%');
            })
            ->update(['status' => 'hidden']);

        DB::table('categories')
            ->where('slug', 'like', 'qa-%')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.category_id', 'categories.id');
            })
            ->delete();
    }

    public function down(): void
    {
        // Keep historical listings and transactions intact; the prior public status is not inferable.
    }
};
