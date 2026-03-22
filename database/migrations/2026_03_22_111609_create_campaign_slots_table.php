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
        Schema::create('campaign_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('campaign_applications')->nullOnDelete();
            $table->foreignId('creator_workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('deliverables')->nullable();
            $table->json('content_brief')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['creator_workspace_id', 'status']);
            $table->index(['application_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_slots');
    }
};
