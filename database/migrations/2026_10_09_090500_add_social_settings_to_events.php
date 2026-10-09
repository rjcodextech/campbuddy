<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin / manager → Event → Social media: the event's brand colours for the
 * post designs (`brand_colors`), and the optional Make.com / Zapier webhook
 * that "Publish" sends a post to (`social_webhook`, stored encrypted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'brand_colors')) {
                $table->json('brand_colors')->nullable();
            }
            if (! Schema::hasColumn('events', 'social_webhook')) {
                $table->text('social_webhook')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            foreach (['brand_colors', 'social_webhook'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
