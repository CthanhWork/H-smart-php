<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 150)->unique();
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('role', 30)->default('member');
            $table->string('full_name', 150)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('status', 30)->default('pending_verification');
            $table->decimal('trust_score', 4, 2)->default(0);
            $table->timestampsTz();
        });

        Schema::create('account_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30);
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'type', 'used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_tokens');
        Schema::dropIfExists('users');
    }
};
