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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('campaign_templates')->nullOnDelete();
            $table->string('title');
            $table->decimal('total_budget', 12, 2)->default(0);
            $table->json('content_brief')->nullable();
            $table->json('deliverables');
            $table->string('status')->default('draft');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['is_public', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
