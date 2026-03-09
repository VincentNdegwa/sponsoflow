<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('revision_count')->default(0)->after('notes');
            $table->unsignedTinyInteger('max_revisions')->default(2)->after('revision_count');
            $table->timestamp('auto_approve_at')->nullable()->after('max_revisions');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['revision_count', 'max_revisions', 'auto_approve_at']);
        });
    }
};
