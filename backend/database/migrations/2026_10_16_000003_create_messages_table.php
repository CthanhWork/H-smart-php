<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'sender_id', 'recipient_id', 'id'], 'messages_thread_idx');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE messages ADD CONSTRAINT messages_distinct_users CHECK (sender_id <> recipient_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
