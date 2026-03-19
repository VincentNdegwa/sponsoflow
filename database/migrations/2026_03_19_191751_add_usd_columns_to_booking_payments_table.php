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
            $table->decimal('amount_usd', 12, 2)->nullable()->after('amount');
            $table->decimal('exchange_rate_to_usd', 20, 10)->nullable()->after('amount_usd');
            $table->string('exchange_rate_provider')->nullable()->after('exchange_rate_to_usd');
            $table->timestamp('exchange_rate_fetched_at')->nullable()->after('exchange_rate_provider');

            $table->index(['status', 'paid_at']);
            $table->index(['currency', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'paid_at']);
            $table->dropIndex(['currency', 'status']);
            $table->dropColumn([
                'amount_usd',
                'exchange_rate_to_usd',
                'exchange_rate_provider',
                'exchange_rate_fetched_at',
            ]);
        });
    }
};
