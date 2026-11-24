<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_vnpay_deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('idempotency_key', 80);
            $table->unsignedBigInteger('amount');
            $table->string('provider', 16)->default('vnpay');
            $table->string('mode', 16)->default('sandbox');
            $table->string('status', 32)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('transaction_no', 64)->nullable();
            $table->string('bank_code', 32)->nullable();
            $table->string('response_code', 8)->nullable();
            $table->unsignedBigInteger('callback_amount')->nullable();
            $table->timestamp('callback_received_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->unique()->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'wallet_vnpay_deposit_idempotency_unique');
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_vnpay_deposits');
    }
};
