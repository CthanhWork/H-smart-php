<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->string('idempotency_key', 80)->unique();
            $table->string('type', 40);
            $table->string('status', 20)->default('posted');
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });
        Schema::create('wallet_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_transaction_id')->constrained('wallet_transactions')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('account_code', 40);
            $table->bigInteger('amount');
            $table->timestamps();
            $table->index(['user_id', 'account_code', 'id'], 'wallet_entries_balance_lookup');
        });
        Schema::create('withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->text('bank_details_ciphertext');
            $table->string('status', 32)->default('pending_review');
            $table->string('reference', 100)->unique();
            $table->text('decision_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->string('idempotency_key', 100);
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
        });
        DB::table('users')->orderBy('id')->chunk(500, function ($users): void {
            $now = now();
            DB::table('wallet_accounts')->insertOrIgnore($users->map(fn ($user) => [
                'user_id' => $user->id, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_accounts');
    }
};
