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
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'campaign_details')) {
                $table->json('campaign_details')->nullable()->after('requirement_data');
            }

            if (! Schema::hasColumn('bookings', 'campaign_deliverables')) {
                $table->json('campaign_deliverables')->nullable()->after('campaign_details');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'campaign_deliverables')) {
                $table->dropColumn('campaign_deliverables');
            }

            if (Schema::hasColumn('bookings', 'campaign_details')) {
                $table->dropColumn('campaign_details');
            }
        });
    }
};
