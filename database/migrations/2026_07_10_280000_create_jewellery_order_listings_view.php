<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jewellery_orders') || ! Schema::hasTable('old_gold_bookings')) {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS jewellery_order_listings');

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE VIEW jewellery_order_listings AS
                SELECT
                    'buy-' || jewellery_orders.id AS listing_key,
                    'buy' AS listing_type,
                    jewellery_orders.id AS source_id,
                    jewellery_orders.order_number AS reference_number,
                    jewellery_orders.user_id,
                    jewellery_orders.status,
                    jewellery_orders.total_amount AS amount,
                    jewellery_orders.driver_id,
                    jewellery_orders.payment_mode,
                    NULL AS product_summary,
                    jewellery_orders.created_at
                FROM jewellery_orders
                UNION ALL
                SELECT
                    'sell-' || old_gold_bookings.id AS listing_key,
                    'sell' AS listing_type,
                    old_gold_bookings.id AS source_id,
                    old_gold_bookings.booking_number AS reference_number,
                    old_gold_bookings.user_id,
                    old_gold_bookings.status,
                    old_gold_bookings.quoted_amount AS amount,
                    old_gold_bookings.driver_id,
                    NULL AS payment_mode,
                    old_gold_bookings.item_name AS product_summary,
                    old_gold_bookings.created_at
                FROM old_gold_bookings
            SQL);

            return;
        }

        DB::statement(<<<'SQL'
            CREATE VIEW jewellery_order_listings AS
            SELECT
                CONCAT('buy-', jo.id) AS listing_key,
                'buy' AS listing_type,
                jo.id AS source_id,
                jo.order_number AS reference_number,
                jo.user_id,
                jo.status,
                jo.total_amount AS amount,
                jo.driver_id,
                jo.payment_mode,
                NULL AS product_summary,
                jo.created_at
            FROM jewellery_orders jo
            UNION ALL
            SELECT
                CONCAT('sell-', og.id) AS listing_key,
                'sell' AS listing_type,
                og.id AS source_id,
                og.booking_number AS reference_number,
                og.user_id,
                og.status,
                og.quoted_amount AS amount,
                og.driver_id,
                NULL AS payment_mode,
                og.item_name AS product_summary,
                og.created_at
            FROM old_gold_bookings og
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS jewellery_order_listings');
    }
};
