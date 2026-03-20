<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->decimal('processor_fee_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('platform_fee_amount', 12, 2)->nullable()->after('processor_fee_amount');
            $table->decimal('escrow_amount', 12, 2)->nullable()->after('platform_fee_amount');
            $table->decimal('creator_payout_amount', 12, 2)->nullable()->after('escrow_amount');
            $table->decimal('creator_released_amount', 12, 2)->nullable()->after('creator_payout_amount');
            $table->timestamp('creator_released_at')->nullable()->after('creator_released_amount');

            $table->index(['status', 'creator_released_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'creator_released_at']);
            $table->dropColumn([
                'processor_fee_amount',
                'platform_fee_amount',
                'escrow_amount',
                'creator_payout_amount',
                'creator_released_amount',
                'creator_released_at',
            ]);
        });
    }
};
