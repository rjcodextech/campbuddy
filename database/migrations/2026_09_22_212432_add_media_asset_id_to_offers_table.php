<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // A real logo image, chosen from the shared Media Library —
            // alongside (not replacing) the emoji `icon` fallback, since
            // not every sponsor deal has a logo worth uploading.
            $table->foreignId('media_asset_id')->nullable()->after('icon')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_asset_id');
        });
    }
};
