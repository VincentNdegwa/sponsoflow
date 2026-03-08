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
        Schema::create('payment_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // The creator/main user
            $table->string('provider'); // 'stripe', 'paypal', etc. for future extensibility
            $table->string('provider_account_id')->nullable(); // Stripe Connect account ID, PayPal merchant ID, etc.
            $table->json('provider_data')->nullable(); // Store provider-specific configuration data
            $table->boolean('is_active')->default(false); // Whether this payment method is active
            $table->boolean('is_verified')->default(false); // Whether provider account is verified (e.g., Stripe Connect onboarding complete)
            $table->timestamp('verified_at')->nullable();
            $table->decimal('platform_fee_percentage', 5, 4)->default(2.0000); // Platform fee percentage
            $table->timestamps();

            $table->unique(['workspace_id', 'provider']);

            $table->index(['workspace_id', 'is_active']);
            $table->index(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_configurations');
    }
};
