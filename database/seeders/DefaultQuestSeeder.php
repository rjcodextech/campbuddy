<?php

namespace Database\Seeders;

use App\Models\Quest;
use Illuminate\Database\Seeder;

/**
 * Default quests apply to every event — event_id stays null.
 * Seeded once; admins edit/extend the list per event from the Quest
 * editor, not by re-running this seeder.
 */
class DefaultQuestSeeder extends Seeder
{
    public function run(): void
    {
        $quests = [
            'Complete your Camp Card' => 'Add your name and at least one link people can scan.',
            'Say hello to someone attending their first WordCamp' => "You'll probably recognize the look — a little unsure where to stand.",
            'Meet someone from another city' => 'Ask where they traveled from and what brought them here.',
            'Visit three sponsor booths' => "They're not just logos — most sponsors are happy to talk about what they build.",
            'Bookmark your afternoon sessions' => 'Build out your My Day schedule before the day gets busy.',
            'Introduce yourself to a speaker' => 'Most speakers love talking more about their topic after the session.',
        ];

        $sortOrder = 0;

        foreach ($quests as $title => $description) {
            Quest::firstOrCreate(
                ['event_id' => null, 'source' => 'default', 'title' => $title],
                ['description' => $description, 'sort_order' => $sortOrder, 'is_active' => true]
            );

            $sortOrder += 10;
        }

        // Contextual, surfaced once the attendee engages with Contribute —
        // matched by title client-side, not a
        // foreign key, since Contribute has no server-side state of its own.
        Quest::firstOrCreate(
            ['event_id' => null, 'source' => 'contributor_day', 'title' => 'Learn what one Contributor Team does'],
            ['description' => 'Open a team on the Contribute tab and read what they do.', 'sort_order' => 5, 'is_active' => true]
        );
    }
}
