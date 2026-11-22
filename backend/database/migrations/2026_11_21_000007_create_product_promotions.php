<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('package_type', 16);
            $table->unsignedBigInteger('fee_amount');
            $table->string('status', 16)->default('active');
            $table->string('reference', 100)->unique();
            $table->string('idempotency_key', 80);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['product_id', 'status', 'starts_at', 'expires_at']);
            $table->index(['seller_id', 'created_at']);
            $table->unique(['seller_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotions');
    }
};
