<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['order_id', 'changed_at']);
        });

        $orders = DB::table('orders')->orderBy('id')->get();
        foreach ($orders as $order) {
            $events = [['pending_payment', $order->created_at]];
            foreach ([
                'paid' => $order->paid_at,
                'processing' => $order->confirmed_at ?? null,
                'shipping' => $order->shipped_at,
                'completed' => $order->completed_at,
                'cancelled' => $order->cancelled_at,
            ] as $status => $at) {
                if ($at) $events[] = [$status, $at];
            }
            if (count($events) === 1 && $order->status !== 'pending_payment') {
                $events[] = [$order->status, $order->updated_at ?? $order->created_at];
            }
            $from = null;
            foreach ($events as [$to, $at]) {
                DB::table('order_status_history')->insert([
                    'order_id' => $order->id, 'from_status' => $from, 'to_status' => $to,
                    'changed_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                ]);
                $from = $to;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
