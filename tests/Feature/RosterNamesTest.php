<?php

namespace Tests\Feature;

use App\Http\Controllers\RosterRemovalController;
use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Services\AttendeeRosterScraper;
use App\Support\PersonName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Names typed on WordCamp sites can hold characters nobody sees ("Arif\u{200B}", found in real data on
 * 30 Oct 2026). Shown they look fine; searched on "Remove me from the list", they never matched — a
 * person could not take their own name down. And the removal token is required, in tests too.
 */
class RosterNamesTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-names', 'display_name' => 'WordCamp Names', 'source_site_url' => 'https://names.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true,
        ]));
    }

    public function test_invisible_characters_and_odd_spaces_are_taken_out_but_indic_joiners_stay(): void
    {
        $this->assertSame('Arif', PersonName::clean("Arif\u{200B}"));
        $this->assertSame('Arif Khan', PersonName::clean("  Arif\u{00A0}\u{00A0} Khan\u{FEFF} "));
        $this->assertSame('Priya Sharma', PersonName::clean("Pri\u{00AD}ya\u{202E} Sharma"));
        // ZWJ / ZWNJ spell Hindi and Malayalam names; they are kept.
        $this->assertSame("क्\u{200D}ष", PersonName::clean("क्\u{200D}ष"));
        $this->assertSame("ന്\u{200C}റ", PersonName::clean("ന്\u{200C}റ"));
        $this->assertSame('arif khan', PersonName::key(" ARIF\u{200B}  Khan"));
    }

    public function test_the_scraper_stores_the_clean_name_but_keeps_the_hash_of_what_the_site_wrote(): void
    {
        $html = '<ul class="tix-attendee-list"><li><div class="tix-attendee-name"><span class="tix-first">Arif'."\u{200B}".'</span> <span class="tix-last"></span></div></li></ul>';
        $scraper = new AttendeeRosterScraper;
        $entry = $scraper->parse($html)[0] ?? null;

        $this->assertNotNull($entry, 'the fixture is the markup the scraper reads');
        $this->assertSame('Arif', $entry['name']);
        $this->assertSame("Arif\u{200B}", $entry['raw_name']);
        // Unchanged hash: an entry someone already removed stays removed after the next refresh.
        $this->assertSame($scraper->contentHash("Arif\u{200B}", []), $scraper->contentHash($entry['raw_name'], $entry['links']));
    }

    public function test_remove_me_finds_a_name_stored_with_an_invisible_character(): void
    {
        $arif = AttendeeRoster::create(['event_id' => $this->event->id, 'name' => "Arif\u{200B}", 'links' => [], 'content_hash' => 'h1', 'is_suppressed' => false]);

        $this->get(route('event.roster-removal.search', [$this->event, 'name' => 'arif']))
            ->assertOk()
            ->assertSee(RosterRemovalController::removalToken($arif), false);
    }

    public function test_a_removal_without_its_token_is_refused_and_with_it_works(): void
    {
        $entry = AttendeeRoster::create(['event_id' => $this->event->id, 'name' => 'Priya Sharma', 'links' => [], 'content_hash' => 'h2', 'is_suppressed' => false]);

        $this->post(route('event.roster-removal.remove', [$this->event, $entry]))->assertForbidden();
        $this->post(route('event.roster-removal.remove', [$this->event, $entry]), ['removal_token' => 'guess'])->assertForbidden();
        $this->assertFalse($entry->fresh()->is_suppressed);

        $this->post(route('event.roster-removal.remove', [$this->event, $entry]), ['removal_token' => RosterRemovalController::removalToken($entry)])->assertRedirect();
        $this->assertTrue($entry->fresh()->is_suppressed);
    }

    public function test_the_search_page_is_never_stored_by_a_browser(): void
    {
        $this->get(route('event.roster-removal.search', [$this->event, 'name' => 'x']))->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('event.roster-removal.show', $this->event))->assertHeader('Cache-Control', 'no-store, private');
    }
}
