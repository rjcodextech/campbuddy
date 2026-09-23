<?php

namespace Database\Seeders;

use App\Models\Quest;
use Illuminate\Database\Seeder;

/**
 * Default quests apply to every event — event_id stays null. These 8
 * double as the "Things to do" rich cards on the Quest tab (see
 * resources/js/attendee/quest.js's THINGS_TO_DO_META, matched by exact
 * title) — event-specific quests admins add per event render in the
 * separate plain Checklist section instead. Titles here are the stable
 * match key for that client-side lookup, so change both together.
 */
class DefaultQuestSeeder extends Seeder
{
    public function run(): void
    {
        $quests = [
            'First Hello' => 'Say hello to someone attending their first WordCamp.',
            'Beyond My City' => 'Meet someone who traveled from another city.',
            'Speaker Hello' => 'Introduce yourself to a speaker after their session.',
            'Contribution Curious' => 'Open a team on the Contribute tab and read what they do.',
            'Sponsor Explore' => "Visit a sponsor booth — they're not just logos, most are happy to talk about what they build.",
            'Asked Something' => 'Ask a question during or after a session.',
            'Keep The Connection' => 'Opt in to attendee discovery and find people who match your interests.',
            'Share Camp Card' => 'Show your Camp Card QR to someone new.',
        ];

        // Titles are the match key for THINGS_TO_DO_META client-side —
        // clear out any older default set so a reseed doesn't leave
        // stale rows that no longer match anything in that lookup.
        Quest::where('event_id', null)
            ->whereIn('source', ['default', 'contributor_day'])
            ->whereNotIn('title', array_keys($quests))
            ->delete();

        $sortOrder = 0;

        foreach ($quests as $title => $description) {
            Quest::updateOrCreate(
                ['event_id' => null, 'source' => 'default', 'title' => $title],
                ['description' => $description, 'sort_order' => $sortOrder, 'is_active' => true]
            );

            $sortOrder += 10;
        }
    }
}
