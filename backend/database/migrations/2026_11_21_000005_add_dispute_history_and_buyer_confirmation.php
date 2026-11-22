<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->timestamp('buyer_confirmed_at')->nullable()->after('delivered_at'));
        Schema::table('order_disputes', function (Blueprint $table): void {
            $table->timestamp('buyer_wallet_refund_consent_at')->nullable()->after('resolution_note');
        });
        Schema::create('order_dispute_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_id')->constrained('order_disputes')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['dispute_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_dispute_events');
        Schema::table('order_disputes', fn (Blueprint $table) => $table->dropColumn('buyer_wallet_refund_consent_at'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('buyer_confirmed_at'));
    }
};
