<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_active');
            $table->integer('featured_order')->default(0)->after('is_public');
            $table->integer('sold_count')->default(0)->after('featured_order');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'featured_order', 'sold_count']);
        });
    }
};
