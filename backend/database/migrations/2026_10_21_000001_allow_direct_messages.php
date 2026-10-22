<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('messages')->whereNull('product_id')->exists()) {
            throw new RuntimeException('Không thể hoàn tác khi còn tin nhắn trực tiếp. Hãy giữ lại dữ liệu trước khi gỡ tính năng này.');
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable(false)->change();
        });
    }
};
