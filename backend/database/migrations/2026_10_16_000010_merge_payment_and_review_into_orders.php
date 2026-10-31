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
            $table->string('payment_txn_ref', 64)->nullable()->unique();
            $table->string('payment_provider', 30)->nullable();
            $table->bigInteger('payment_amount')->nullable();
            $table->string('payment_status', 30)->nullable();
            $table->string('payment_transaction_no', 64)->nullable()->unique();
            $table->string('payment_response_code', 10)->nullable();
            $table->string('payment_bank_code', 30)->nullable();
            $table->timestamp('payment_paid_at')->nullable();
            $table->unsignedTinyInteger('review_rating')->nullable();
            $table->string('review_comment', 1000)->nullable();
            $table->timestamp('review_created_at')->nullable();
            $table->timestamp('review_updated_at')->nullable();
        });

        DB::table('payments')->orderBy('id')->chunk(100, function ($payments): void {
            foreach ($payments as $payment) {
                DB::table('orders')->where('id', $payment->order_id)->update([
                    'payment_txn_ref' => $payment->txn_ref,
                    'payment_provider' => $payment->provider,
                    'payment_amount' => $payment->amount,
                    'payment_status' => $payment->status,
                    'payment_transaction_no' => $payment->transaction_no,
                    'payment_response_code' => $payment->response_code,
                    'payment_bank_code' => $payment->bank_code,
                    'payment_paid_at' => $payment->paid_at,
                ]);
            }
        });

        DB::table('reviews')->orderBy('id')->chunk(100, function ($reviews): void {
            foreach ($reviews as $review) {
                DB::table('orders')->where('id', $review->order_id)->update([
                    'review_rating' => $review->rating,
                    'review_comment' => $review->comment,
                    'review_created_at' => $review->created_at,
                    'review_updated_at' => $review->updated_at,
                ]);
            }
        });

        Schema::drop('reviews');
        Schema::drop('payments');

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_review_rating_valid CHECK (review_rating IS NULL OR review_rating BETWEEN 1 AND 5)');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_valid CHECK (payment_status IS NULL OR payment_status IN ('pending','paid','failed','expired'))");
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_payment_amount_valid CHECK (payment_amount IS NULL OR payment_amount > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders DROP CHECK orders_review_rating_valid');
            DB::statement('ALTER TABLE orders DROP CHECK orders_payment_status_valid');
            DB::statement('ALTER TABLE orders DROP CHECK orders_payment_amount_valid');
        }

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->string('txn_ref', 64)->unique();
            $table->string('provider', 30)->default('vnpay');
            $table->bigInteger('amount');
            $table->string('status', 30)->default('pending');
            $table->string('transaction_no', 64)->nullable()->unique();
            $table->string('response_code', 10)->nullable();
            $table->string('bank_code', 30)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 1000)->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        DB::table('orders')->orderBy('id')->chunk(100, function ($orders): void {
            foreach ($orders as $order) {
                if ($order->payment_status !== null) {
                    DB::table('payments')->insert([
                        'order_id' => $order->id,
                        'txn_ref' => $order->payment_txn_ref,
                        'provider' => $order->payment_provider ?? 'vnpay',
                        'amount' => $order->payment_amount ?? $order->total_amount,
                        'status' => $order->payment_status,
                        'transaction_no' => $order->payment_transaction_no,
                        'response_code' => $order->payment_response_code,
                        'bank_code' => $order->payment_bank_code,
                        'paid_at' => $order->payment_paid_at,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ]);
                }
                if ($order->review_rating !== null) {
                    DB::table('reviews')->insert([
                        'order_id' => $order->id,
                        'product_id' => $order->product_id,
                        'buyer_id' => $order->buyer_id,
                        'rating' => $order->review_rating,
                        'comment' => $order->review_comment,
                        'created_at' => $order->review_created_at,
                        'updated_at' => $order->review_updated_at,
                    ]);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_payment_txn_ref_unique');
            $table->dropUnique('orders_payment_transaction_no_unique');
            $table->dropColumn([
                'payment_txn_ref', 'payment_provider', 'payment_amount', 'payment_status', 'payment_transaction_no',
                'payment_response_code', 'payment_bank_code', 'payment_paid_at', 'review_rating',
                'review_comment', 'review_created_at', 'review_updated_at',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_valid CHECK (status IN ('pending','paid','failed','expired'))");
        }
    }
};
