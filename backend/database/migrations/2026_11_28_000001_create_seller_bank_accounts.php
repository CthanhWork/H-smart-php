<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->text('details_ciphertext');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('seller_bank_accounts'); }
};
