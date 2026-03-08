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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('paystack_reference')->nullable()->after('stripe_session_id');
            $table->string('paystack_access_code')->nullable()->after('paystack_reference');
            $table->string('paystack_transaction_id')->nullable()->after('paystack_access_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['paystack_reference', 'paystack_access_code', 'paystack_transaction_id']);
        });
    }
};
