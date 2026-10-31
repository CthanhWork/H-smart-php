<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            // MySQL has no partial unique index; NULL allows cancelled orders.
            $table->unsignedBigInteger('active_product_id')->nullable()->storedAs(
                "CASE WHEN `status` IN ('pending_payment','paid','processing','shipping','completed') THEN `product_id` ELSE NULL END"
            );
            $table->unique('active_product_id', 'uq_orders_active_product');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('uq_orders_active_product');
            $table->dropColumn('active_product_id');
        });
    }
};
