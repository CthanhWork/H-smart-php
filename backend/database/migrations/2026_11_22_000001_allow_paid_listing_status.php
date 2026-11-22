<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PREVIOUS_STATUSES = "'draft','pending_review','active','reserved','sold','hidden'";

    private const CURRENT_STATUSES = "'draft','pending_review','pending_listing_payment','active','reserved','sold','hidden'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE products DROP CHECK products_status_valid');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_status_valid CHECK (`status` IN ('.self::CURRENT_STATUSES.'))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (DB::table('products')->where('status', 'pending_listing_payment')->exists()) {
            throw new RuntimeException('Cannot roll back while products are waiting for listing fee payment.');
        }

        DB::statement('ALTER TABLE products DROP CHECK products_status_valid');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_status_valid CHECK (`status` IN ('.self::PREVIOUS_STATUSES.'))');
    }
};
