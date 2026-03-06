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
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('slot_date');
            $table->time('slot_time')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('status')->default('available');
            $table->timestamp('reserved_until')->nullable();
            $table->json('brand_assets')->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_url')->nullable();
            $table->timestamp('proof_submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'slot_date', 'status']);
            $table->index(['workspace_id', 'status']);
            $table->index(['booked_by_user_id']);
            $table->unique(['product_id', 'slot_date', 'slot_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
