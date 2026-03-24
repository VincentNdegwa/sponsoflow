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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->timestamp('posted_at')->nullable();
        });

        DB::table('campaigns')
            ->whereNull('posted_at')
            ->update(['posted_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasDescription = Schema::hasColumn('campaigns', 'description');
        $hasPostedAt = Schema::hasColumn('campaigns', 'posted_at');

        if (! $hasDescription && ! $hasPostedAt) {
            return;
        }

        Schema::table('campaigns', function (Blueprint $table) use ($hasDescription, $hasPostedAt) {
            if ($hasDescription) {
                $table->dropColumn('description');
            }

            if ($hasPostedAt) {
                $table->dropColumn('posted_at');
            }
        });
    }
};
