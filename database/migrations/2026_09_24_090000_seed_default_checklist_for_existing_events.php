<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New events get the default checklist from EventObserver::created; this
 * gives the same nine items to every event that already exists. A snapshot
 * on purpose (not Quest::DEFAULT_CHECKLIST) — a migration must keep doing
 * what it did on the day it ran, even if that list is edited later.
 *
 * Skips titles an event already has, so it's safe against an event whose
 * admin got there first.
 */
return new class extends Migration
{
    private const CHECKLIST = [
        'Save venue directions',
        'Confirm your WordCamp ticket',
        'Register for Contributor Day if attending',
        'Create / check your WordPress.org account',
        'Join Make WordPress Slack',
        'Pack laptop + charger',
        'Add your details to Camp Card',
        "Pick a few sessions you don't want to miss",
        'Prepare a 15-second introduction',
    ];

    public function up(): void
    {
        $now = now();

        foreach (DB::table('events')->pluck('id') as $eventId) {
            $existing = DB::table('quests')
                ->where('event_id', $eventId)
                ->where('source', 'event')
                ->pluck('title')
                ->all();

            foreach (self::CHECKLIST as $index => $title) {
                if (in_array($title, $existing, true)) {
                    continue;
                }

                DB::table('quests')->insert([
                    'event_id' => $eventId,
                    'source' => 'event',
                    'title' => $title,
                    'description' => null,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible: by now admins may have edited or deleted these
        // rows, and removing "the default ones" by title could take out
        // quests they wrote themselves.
    }
};
