<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('old_gold_bookings', function (Blueprint $table) {
            $table->string('metal_type', 20)->nullable()->after('user_id');
            $table->string('purity', 20)->nullable()->after('metal_type');
            $table->string('item_name')->nullable()->after('purity');
            $table->decimal('rate_per_gram', 14, 2)->nullable()->after('estimated_weight_grams');
            $table->string('identity_owner', 30)->nullable()->after('quoted_amount');
            $table->string('sell_location', 30)->nullable()->after('identity_owner');
            $table->foreignId('user_address_id')->nullable()->after('sell_location')->constrained('user_addresses')->nullOnDelete();
            $table->string('pickup_name')->nullable()->after('pickup_address');
            $table->string('pickup_phone', 20)->nullable()->after('pickup_name');
            $table->json('documents')->nullable()->after('pickup_phone');
            $table->timestamp('accepted_at')->nullable()->after('admin_notes');
            $table->timestamp('pickup_scheduled_at')->nullable()->after('accepted_at');
            $table->timestamp('picked_up_at')->nullable()->after('pickup_scheduled_at');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE old_gold_bookings MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('old_gold_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_address_id');
            $table->dropColumn([
                'metal_type',
                'purity',
                'item_name',
                'rate_per_gram',
                'identity_owner',
                'sell_location',
                'pickup_name',
                'pickup_phone',
                'documents',
                'accepted_at',
                'pickup_scheduled_at',
                'picked_up_at',
            ]);
        });
    }
};
