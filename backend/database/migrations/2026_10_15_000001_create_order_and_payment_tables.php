<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->restrictOnDelete();
            $table->bigInteger('product_amount');
            $table->bigInteger('shipping_fee')->default(0);
            $table->bigInteger('platform_fee')->default(0);
            $table->bigInteger('total_amount');
            $table->string('status', 30)->default('pending_payment');
            $table->json('shipping_address_snapshot');
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['buyer_id', 'created_at']);
            $table->index(['seller_id', 'created_at']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->string('txn_ref', 64)->unique();
            $table->string('provider', 30)->default('vnpay');
            $table->bigInteger('amount');
            $table->string('status', 30)->default('pending');
            $table->string('transaction_no', 64)->nullable()->unique();
            $table->string('response_code', 10)->nullable();
            $table->string('bank_code', 30)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_buyer_seller_different CHECK (buyer_id <> seller_id)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_amounts_valid CHECK (product_amount >= 0 AND shipping_fee >= 0 AND platform_fee >= 0 AND total_amount = product_amount + shipping_fee)');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (`status` IN ('pending_payment','paid','processing','shipping','completed','cancelled'))");
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_valid CHECK (`status` IN ('pending','paid','failed','expired'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
    }
};
