<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->nullable()->after('paid_at');
            $table->string('shipping_carrier', 80)->nullable()->after('shipped_at');
            $table->string('tracking_number', 80)->nullable()->after('shipping_carrier');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['confirmed_at', 'shipping_carrier', 'tracking_number']);
        });
    }
};
