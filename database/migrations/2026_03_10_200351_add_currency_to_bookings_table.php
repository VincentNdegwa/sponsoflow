<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('amount_paid');
            }
        });

        // Back-fill existing rows with the creator workspace's currency.
        DB::table('bookings')->get(['id', 'workspace_id'])->each(function ($booking) {
            $workspace = DB::table('workspaces')->where('id', $booking->workspace_id)->value('currency');
            if ($workspace) {
                DB::table('bookings')->where('id', $booking->id)->update(['currency' => $workspace]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
