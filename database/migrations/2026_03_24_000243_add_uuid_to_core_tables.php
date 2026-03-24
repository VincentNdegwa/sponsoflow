<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'products',
            'campaigns',
            'bookings',
            'booking_payments',
            'booking_submissions',
            'campaign_applications',
            'campaign_slots',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->ulid('uuid')->unique()->nullable()->after('id');
            });
        }

        foreach ($tables as $tableName) {
            $this->backfillTableUlids($tableName);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'products',
            'campaigns',
            'bookings',
            'booking_payments',
            'booking_submissions',
            'campaign_applications',
            'campaign_slots',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $indexName = $tableName.'_uuid_unique';
                if (Schema::hasIndex($tableName, $indexName)) {
                    $table->dropUnique($indexName);
                }

                $table->dropColumn('uuid');
            });
        }
    }

    private function backfillTableUlids(string $tableName): void
    {
        DB::table($tableName)
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($tableName): void {
                foreach ($rows as $row) {
                    DB::table($tableName)
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::ulid()]);
                }
            });
    }
};
