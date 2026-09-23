<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event Information (events.info) is now filled in automatically from the
 * event's own WordCamp site (FetchEventInfoJob). To keep admin edits safe,
 * the job remembers exactly what it last wrote here: a field whose current
 * value differs from this snapshot was typed by an admin and is left alone;
 * one that still matches is auto-filled and gets refreshed (or blanked, if
 * the source no longer has it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('info_fetched')->nullable()->after('info');
            $table->timestamp('info_fetched_at')->nullable()->after('info_fetched');
        });

        // Discovery used to write a lone "venue" (the event's city) into
        // `info`. That's machine-filled, not admin content, so mark it as
        // auto-filled and let the first fetch replace it with the real
        // venue. Any event with more than that in `info` was edited by an
        // admin — all of it stays theirs.
        DB::table('events')->whereNotNull('info')->orderBy('id')->each(function ($event) {
            $info = json_decode($event->info, true);

            if (is_array($info) && array_keys($info) === ['venue']) {
                DB::table('events')->where('id', $event->id)->update(['info_fetched' => $event->info]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['info_fetched', 'info_fetched_at']);
        });
    }
};
