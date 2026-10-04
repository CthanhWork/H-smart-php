<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('slug', 180)->unique();
            $table->timestampsTz();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->bigInteger('price');
            $table->string('condition', 30);
            $table->boolean('negotiable')->default(false);
            $table->bigInteger('min_price')->nullable();
            $table->string('status', 30)->default('pending_review');
            $table->string('province', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->timestampsTz();
            $table->index(['seller_id', 'created_at']);
            $table->index(['category_id', 'status']);
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->text('url');
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_positive CHECK (price > 0)');
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_condition_valid CHECK (condition IN ('new','like_new','good','fair'))");
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_min_price_valid CHECK (min_price IS NULL OR (min_price > 0 AND min_price <= price))');
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_valid CHECK (status IN ('draft','pending_review','active','reserved','sold','hidden'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
