<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memories (App\Support\EventMemories): the official photo album and the
 * event's talks on WordPress.tv, added after the event by an admin or its
 * managers, and when each one's push notification went out (once per kind).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'photo_album_url')) {
                $table->string('photo_album_url', 500)->nullable();
                $table->timestamp('photo_album_notified_at')->nullable();
            }
            if (! Schema::hasColumn('events', 'wordpress_tv_url')) {
                $table->string('wordpress_tv_url', 500)->nullable();
                $table->timestamp('wordpress_tv_notified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            foreach (['photo_album_url', 'photo_album_notified_at', 'wordpress_tv_url', 'wordpress_tv_notified_at'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
