<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The WordCamp site's own look, read from its home page for the Social media
 * designs (App\Support\SiteTheme): its colours, fonts and artwork.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'site_theme')) {
            Schema::table('events', function (Blueprint $table) {
                $table->json('site_theme')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'site_theme')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('site_theme');
            });
        }
    }
};
