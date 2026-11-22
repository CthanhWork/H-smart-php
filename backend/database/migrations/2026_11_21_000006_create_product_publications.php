<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('month_key', 7);
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->boolean('used_free_quota')->default(false);
            $table->string('status', 24)->default('pending_payment');
            $table->string('reference', 100)->unique();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'month_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_publications');
    }
};
