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
        if (! Schema::hasTable('campaign_slots')) {
            return;
        }

        Schema::table('campaign_slots', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_slots', 'unit_price')) {
                $table->dropColumn('unit_price');
            }

            if (Schema::hasColumn('campaign_slots', 'quantity')) {
                $table->dropColumn('quantity');
            }

            if (Schema::hasColumn('campaign_slots', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('campaign_slots')) {
            return;
        }

        Schema::table('campaign_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('campaign_slots', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('campaign_slots', 'quantity')) {
                $table->integer('quantity')->default(1);
            }

            if (! Schema::hasColumn('campaign_slots', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0);
            }
        });
    }
};
