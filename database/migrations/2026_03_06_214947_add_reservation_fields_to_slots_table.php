<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->timestamp('reserved_at')->nullable()->after('notes');
            $table->string('reserved_by')->nullable()->after('reserved_at');
            $table->string('stripe_session_id')->nullable()->after('reserved_by');
        });
    }

    public function down(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->dropColumn(['reserved_at', 'reserved_by', 'stripe_session_id']);
        });
    }
};
