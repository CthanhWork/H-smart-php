<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table): void {
            $table->json('search_context')->nullable();
        });
        Schema::table('assistant_messages', function (Blueprint $table): void {
            $table->json('structured_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assistant_messages', fn (Blueprint $table) => $table->dropColumn('structured_data'));
        Schema::table('assistant_conversations', fn (Blueprint $table) => $table->dropColumn('search_context'));
    }
};
