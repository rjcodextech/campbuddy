<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Free Steal can show its own logo, picked from the Media Library like a
 * deal's. Without one the card keeps its category icon (FreeSteal::icon).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('free_steals', function (Blueprint $table) {
            $table->foreignId('media_asset_id')->nullable()->after('category')->constrained('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('free_steals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_asset_id');
        });
    }
};
