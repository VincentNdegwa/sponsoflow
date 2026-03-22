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
        Schema::create('campaign_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('creator_workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('status')->default('submitted');
            $table->json('notes')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['creator_workspace_id', 'status']);
            $table->unique(['campaign_id', 'creator_workspace_id', 'product_id'], 'campaign_creator_product_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_applications');
    }
};
