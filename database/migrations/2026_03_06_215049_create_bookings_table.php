<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('brand_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('brand_workspace_id')->nullable()->constrained('workspaces')->onDelete('set null');
            $table->string('guest_email');
            $table->string('guest_name');
            $table->string('guest_company')->nullable();
            $table->json('requirement_data');
            $table->decimal('amount_paid', 10, 2);
            $table->string('stripe_payment_intent_id');
            $table->string('stripe_session_id');
            $table->enum('status', ['pending', 'confirmed', 'processing', 'completed', 'cancelled', 'disputed'])->default('pending');
            $table->boolean('account_claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
