<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('purchase_type', 20)->default('offer')->after('offer_id');
            $table->string('idempotency_key', 80)->nullable()->unique()->after('purchase_type');
            $table->bigInteger('fee_base')->default(0)->after('platform_fee');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('release_eligible_at')->nullable()->after('delivered_at');
            $table->string('payout_status', 24)->default('not_ready')->after('payment_status');
        });
        DB::table('orders')->where('fee_base', 0)->update(['fee_base' => DB::raw('product_amount')]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['purchase_type', 'idempotency_key', 'fee_base', 'delivered_at', 'release_eligible_at', 'payout_status']));
        Schema::dropIfExists('cart_items');
    }
};
