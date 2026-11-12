<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedTinyInteger('commission_rate')->default(0)->after('platform_fee');
            $table->string('delivery_status', 24)->nullable()->after('tracking_number');
            $table->string('delivery_failed_reason', 500)->nullable()->after('delivery_status');
            $table->timestamp('returned_at')->nullable()->after('delivery_failed_reason');
            $table->timestamp('cod_collected_at')->nullable()->after('delivery_failed_reason');
            $table->timestamp('carrier_settled_at')->nullable()->after('cod_collected_at');
            $table->timestamp('seller_paid_at')->nullable()->after('carrier_settled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'commission_rate', 'delivery_status', 'delivery_failed_reason', 'returned_at',
                'cod_collected_at', 'carrier_settled_at', 'seller_paid_at',
            ]);
        });
    }
};
