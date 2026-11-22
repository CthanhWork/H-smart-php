<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_method', 20)->default('vnpay')->after('payment_provider');
            $table->unsignedTinyInteger('cod_deposit_percent')->nullable()->after('payment_method');
            $table->unsignedBigInteger('cod_deposit_amount')->default(0)->after('cod_deposit_percent');
            $table->unsignedBigInteger('cod_remaining_amount')->default(0)->after('cod_deposit_amount');
            $table->timestamp('cod_remaining_collected_at')->nullable()->after('cod_collected_at');
            $table->string('cod_remaining_collection_type', 24)->nullable()->after('cod_remaining_collected_at');
            $table->timestamp('cod_deposit_refunded_at')->nullable()->after('cod_remaining_collection_type');
            $table->timestamp('cod_deposit_wallet_consent_at')->nullable()->after('cod_deposit_refunded_at');
            $table->string('policy_version', 32)->nullable()->after('cod_deposit_refunded_at');
            $table->timestamp('policy_accepted_at')->nullable()->after('policy_version');
            $table->index(['payment_method', 'status']);
        });
        DB::table('orders')->where('payment_provider', 'cod')->update([
            'payment_method' => 'cod',
            'cod_remaining_amount' => DB::raw('total_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['payment_method', 'status']);
            $table->dropColumn(['payment_method', 'cod_deposit_percent', 'cod_deposit_amount', 'cod_remaining_amount', 'cod_remaining_collected_at', 'cod_remaining_collection_type', 'cod_deposit_refunded_at', 'cod_deposit_wallet_consent_at', 'policy_version', 'policy_accepted_at']);
        });
    }
};
