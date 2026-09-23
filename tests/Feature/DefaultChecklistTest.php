<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultChecklistTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
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

    private function makeEvent(string $slug = 'wc-test', array $overrides = []): Event
    {
        return Event::create($overrides + [
            'slug' => $slug,
            'display_name' => 'WordCamp Test',
            'source_site_url' => "https://{$slug}.example.org",
            'status' => 'active',
            'is_visible' => true,
        ]);
    }

    private function checklistOf(Event $event): array
    {
        return $event->quests()->where('source', 'event')->orderBy('sort_order')->pluck('title')->all();
    }

    public function test_a_new_event_starts_with_the_default_checklist_in_order(): void
    {
        $event = $this->makeEvent();

        $this->assertSame(self::EXPECTED, $this->checklistOf($event));
        $this->assertSame(
            [10, 20, 30, 40, 50, 60, 70, 80, 90],
            $event->quests()->orderBy('sort_order')->pluck('sort_order')->all()
        );
        $this->assertSame(9, $event->quests()->where('is_active', true)->count());
    }

    public function test_each_event_gets_its_own_copies(): void
    {
        $a = $this->makeEvent('wc-a');
        $b = $this->makeEvent('wc-b');

        $a->quests()->where('title', 'Pack laptop + charger')->update(['title' => 'Pack laptop, charger and umbrella']);

        $this->assertContains('Pack laptop + charger', $this->checklistOf($b));
        $this->assertNotContains('Pack laptop + charger', $this->checklistOf($a));
    }

    public function test_seeding_again_only_fills_in_what_is_missing(): void
    {
        $event = $this->makeEvent();
        $event->quests()->where('title', 'Join Make WordPress Slack')->delete();

        $event->seedDefaultChecklist();
        $event->seedDefaultChecklist();

        $this->assertCount(9, $this->checklistOf($event));
    }

    public function test_the_defaults_do_not_collide_with_the_things_to_do_cards(): void
    {
        // quest.js routes a quest to a rich "Things to do" card by exact
        // title; a default checklist item sharing one would vanish into it.
        preg_match_all("/^\s*'([^']+)':\s*\{ icon:/m", file_get_contents(resource_path('js/attendee/quest.js')), $m);

        $this->assertNotEmpty($m[1], 'Could not read THINGS_TO_DO_META from quest.js.');
        $this->assertSame([], array_intersect(Quest::DEFAULT_CHECKLIST, $m[1]));
    }

    public function test_existing_events_are_backfilled_by_the_migration(): void
    {
        $event = $this->makeEvent();
        $other = $this->makeEvent('wc-other');

        // Put both back to how they were before this shipped: one bare,
        // one whose admin had already added a quest and one of the titles.
        Quest::query()->delete();
        $other->quests()->create(['source' => 'event', 'title' => 'Bring a notebook', 'sort_order' => 0]);
        $other->quests()->create(['source' => 'event', 'title' => 'Save venue directions', 'sort_order' => 0]);

        $migration = require database_path('migrations/2026_09_24_090000_seed_default_checklist_for_existing_events.php');
        $migration->up();
        $migration->up(); // running it twice must not duplicate anything

        $this->assertSame(self::EXPECTED, $this->checklistOf($event));
        $this->assertCount(10, $this->checklistOf($other)); // 9 defaults + their own quest
        $this->assertSame(1, $other->quests()->where('title', 'Save venue directions')->count());
        $this->assertContains('Bring a notebook', $this->checklistOf($other));
    }

    public function test_attendees_see_the_default_checklist_on_the_quest_page(): void
    {
        $this->withoutVite();
        $event = $this->makeEvent();

        $response = $this->get(route('event.quest', $event))->assertOk();

        foreach (self::EXPECTED as $title) {
            $response->assertSee(json_encode($title, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), false);
        }
    }

    public function test_an_admin_can_edit_and_remove_a_default_item_and_add_their_own_after_them(): void
    {
        $event = $this->makeEvent();
        $admin = User::factory()->create();
        $item = $event->quests()->where('title', 'Pack laptop + charger')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.events.quests.update', [$event, $item]), ['title' => 'Pack laptop, charger and badge', 'description' => 'Bring the lanyard too.', 'is_active' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('quests', ['id' => $item->id, 'title' => 'Pack laptop, charger and badge', 'description' => 'Bring the lanyard too.']);

        $this->actingAs($admin)
            ->delete(route('admin.events.quests.destroy', [$event, $item]))
            ->assertRedirect();

        $this->assertDatabaseMissing('quests', ['id' => $item->id]);

        $this->actingAs($admin)
            ->post(route('admin.events.quests.store', $event), ['title' => 'Bring business cards'])
            ->assertRedirect();

        $titles = $this->checklistOf($event);
        $this->assertSame('Bring business cards', end($titles), 'A quest the admin adds should land after the defaults.');
    }
}
