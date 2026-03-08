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
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('country_code', 2)->default('US')->after('slug');
            $table->string('currency', 3)->default('USD')->after('country_code');
            $table->string('timezone')->default('UTC')->after('currency');
            $table->string('date_format')->default('M d, Y')->after('timezone');
            $table->string('time_format')->default('g:i A')->after('date_format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'currency', 'timezone', 'date_format', 'time_format']);
        });
    }
};
