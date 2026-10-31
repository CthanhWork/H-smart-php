<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('offer_price');
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });

        // Add constraints
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE offers ADD CONSTRAINT offers_price_positive CHECK (offer_price > 0)');
            DB::statement('ALTER TABLE offers ADD CONSTRAINT offers_buyer_seller_different CHECK (buyer_id <> seller_id)');
            DB::statement("ALTER TABLE offers ADD CONSTRAINT offers_status_valid CHECK (`status` IN ('pending','accepted','rejected','cancelled','expired','ordered'))");

            // MySQL doesn't support partial indexes with WHERE clause
            // We'll enforce uniqueness in application logic instead
            // Add composite index for performance
            DB::statement('CREATE INDEX idx_offers_buyer_product_status ON offers (buyer_id, product_id, `status`)');
            DB::statement('CREATE INDEX idx_offers_product_status ON offers (product_id, `status`)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
