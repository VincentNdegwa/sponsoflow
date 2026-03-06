<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('public_slug')->unique()->nullable()->after('email_verified_at');
            $table->text('public_bio')->nullable()->after('public_slug');
            $table->string('profile_image')->nullable()->after('public_bio');
            $table->boolean('is_public_profile')->default(false)->after('profile_image');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['public_slug', 'public_bio', 'profile_image', 'is_public_profile']);
        });
    }
};
