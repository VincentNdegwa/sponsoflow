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
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // 'stripe', 'paystack', etc.
            $table->string('provider_transaction_id')->nullable(); // External transaction ID
            $table->string('provider_reference')->unique(); // Our internal reference
            $table->string('session_id')->nullable(); // Session/checkout ID
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            $table->decimal('amount', 10, 2); // Payment amount
            $table->decimal('amount_usd', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD'); // Currency code
            $table->decimal('exchange_rate_to_usd', 20, 10)->nullable();
            $table->string('exchange_rate_provider')->nullable();
            $table->timestamp('exchange_rate_fetched_at')->nullable();
            $table->json('amount_breakdown')->nullable(); // local + USD breakdown values
            $table->json('provider_data')->nullable(); // Store provider-specific data
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('creator_released_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'provider']);
            $table->index(['provider', 'status']);
            $table->index(['provider_transaction_id']);
            $table->index(['status', 'paid_at']);
            $table->index(['currency', 'status']);
            $table->index(['status', 'creator_released_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
