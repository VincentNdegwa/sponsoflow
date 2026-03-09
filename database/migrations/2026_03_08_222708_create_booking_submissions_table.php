<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('work_url')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->text('revision_notes')->nullable();
            $table->unsignedTinyInteger('revision_number')->default(0);
            $table->timestamp('auto_approve_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_submissions');
    }
};
