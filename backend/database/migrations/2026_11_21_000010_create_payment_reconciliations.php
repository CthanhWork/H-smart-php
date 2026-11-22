<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24);
            $table->string('payment_reference', 100)->index();
            $table->string('bank_reference', 100);
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('unmatched');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reconciled_by')->constrained('users')->restrictOnDelete();
            $table->text('evidence_note');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'bank_reference']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
    }
};
