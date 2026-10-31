<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ([
            ['Điện tử', 'dien-tu'],
            ['Sách và giáo trình', 'sach-va-giao-trinh'],
            ['Đồ dùng học tập', 'do-dung-hoc-tap'],
            ['Thời trang', 'thoi-trang'],
            ['Đồ gia dụng', 'do-gia-dung'],
            ['Khác', 'khac'],
        ] as [$name, $slug]) {
            DB::table('categories')->updateOrInsert(['slug' => $slug], ['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('categories')->whereIn('slug', ['dien-tu', 'sach-va-giao-trinh', 'do-dung-hoc-tap', 'thoi-trang', 'do-gia-dung', 'khac'])->delete();
    }
};
