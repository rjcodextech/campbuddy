<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Support\EventData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The public lists (roster, discovery, data-version) are built once and served
 * to every phone: cheap for the origin, and a phone that already has the
 * current version gets an empty 304. What is personal (waves, messages) and the
 * admin's purge signal (cache-version) must never be shared or held.
 */
class ApiCachingTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-10T12:00:00Z'));

        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-caching', 'display_name' => 'WordCamp Caching', 'source_site_url' => 'https://caching.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'timezone' => 'UTC',
        ]));

        foreach (['Asha Rao', 'Ben Lee', 'Chen Wei'] as $name) {
            AttendeeRoster::create([
                'event_id' => $this->event->id, 'name' => $name, 'gravatar_url' => null, 'links' => [],
                'content_hash' => hash('sha256', $name), 'is_suppressed' => false,
            ]);
        }
    }

    /** @return array<int, string> the tables the queries run in this callback touched, in order */
    private function queriedTables(callable $run): array
    {
        $tables = [];
        DB::listen(function ($query) use (&$tables) {
            if (preg_match('/\b(?:from|into|update)\s+[`"]?(\w+)/i', $query->sql, $m)) {
                $tables[] = $m[1];
            }
        });
        $run();

        return $tables;
    }

    private function url(string $path): string
    {
        return "/api/v1/events/{$this->event->slug}/{$path}";
    }

    // ---- Roster ------------------------------------------------------------------

    public function test_the_roster_carries_validators_and_shared_cache_headers(): void
    {
        $response = $this->getJson($this->url('roster'))->assertOk();

        $this->assertStringStartsWith('W/"', $response->headers->get('ETag'));
        $cache = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=0', $cache, 'a browser always revalidates');
        $this->assertStringContainsString('s-maxage=30', $cache);
        $this->assertSame(['Asha Rao', 'Ben Lee', 'Chen Wei'], array_column($response->json('data'), 'name'));
        $this->assertSame(1, $response->json('last_page'));
    }

    public function test_a_phone_with_the_current_roster_gets_an_empty_304(): void
    {
        $etag = $this->getJson($this->url('roster'))->headers->get('ETag');

        $again = $this->getJson($this->url('roster'), ['If-None-Match' => $etag]);

        $again->assertStatus(304);
        $this->assertSame('', $again->getContent());
        $this->assertSame($etag, $again->headers->get('ETag'));
        $this->assertStringContainsString('s-maxage=30', $again->headers->get('Cache-Control'));
    }

    public function test_a_roster_page_is_built_once_however_many_phones_ask(): void
    {
        $this->getJson($this->url('roster'))->assertOk();

        $tables = $this->queriedTables(function () {
            foreach (range(1, 5) as $ignored) {
                $this->getJson($this->url('roster'))->assertOk();
            }
        });

        $this->assertNotContains('attendee_roster', $tables, 'served from the built page, not the database');
    }

    public function test_the_roster_is_rebuilt_after_a_minute_and_an_old_validator_then_gets_the_new_page(): void
    {
        $old = $this->getJson($this->url('roster'))->headers->get('ETag');

        AttendeeRoster::create([
            'event_id' => $this->event->id, 'name' => 'Dev Patel', 'gravatar_url' => null, 'links' => [],
            'content_hash' => hash('sha256', 'Dev Patel'), 'is_suppressed' => false,
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-10-10T12:01:05Z'));

        $fresh = $this->getJson($this->url('roster'), ['If-None-Match' => $old]);

        $fresh->assertOk();
        $this->assertContains('Dev Patel', array_column($fresh->json('data'), 'name'));
        $this->assertNotSame($old, $fresh->headers->get('ETag'));
    }

    public function test_absurd_roster_page_numbers_are_harmless(): void
    {
        $this->getJson($this->url('roster').'?page=999999')->assertOk()->assertJson(['data' => []]);
        $this->getJson($this->url('roster').'?page=-5')->assertOk();
        $this->getJson($this->url('roster').'?page=abc')->assertOk();
    }

    // ---- Discovery list --------------------------------------------------------------

    private function join(array $body = []): array
    {
        return $this->postJson($this->url('discovery'), $body + ['tags' => ['developer']])->assertCreated()->json();
    }

    public function test_the_discovery_list_is_shared_and_revalidated(): void
    {
        $response = $this->getJson($this->url('discovery'))->assertOk();

        $etag = $response->headers->get('ETag');
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('s-maxage=15', $response->headers->get('Cache-Control'));
        $this->getJson($this->url('discovery'), ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_joining_updates_the_discovery_list_at_once_not_after_the_cache_expires(): void
    {
        $before = $this->getJson($this->url('discovery'));
        $this->assertSame([], $before->json('data'));

        $card = $this->join();

        $after = $this->getJson($this->url('discovery'), ['If-None-Match' => $before->headers->get('ETag')]);
        $after->assertOk();
        $this->assertSame([$card['discovery_id']], array_column($after->json('data'), 'discovery_id'));
    }

    public function test_editing_and_leaving_update_the_discovery_list_at_once(): void
    {
        $card = $this->join(['profession' => 'Designer']);
        $this->getJson($this->url('discovery'))->assertOk();

        $this->withToken($card['owner_token'])->patchJson($this->url("discovery/{$card['discovery_id']}"), ['tags' => ['developer'], 'profession' => 'Plugin developer'])->assertOk();
        $this->assertSame('Plugin developer', $this->getJson($this->url('discovery'))->json('data.0.fields.profession'));

        $this->withToken($card['owner_token'])->deleteJson($this->url("discovery/{$card['discovery_id']}"))->assertNoContent();
        $this->assertSame([], $this->getJson($this->url('discovery'))->json('data'));
    }

    public function test_the_discovery_list_is_built_once_however_many_phones_ask(): void
    {
        $this->join();
        $this->getJson($this->url('discovery'))->assertOk();

        $tables = $this->queriedTables(function () {
            foreach (range(1, 5) as $ignored) {
                $this->getJson($this->url('discovery'))->assertOk();
            }
        });

        $this->assertNotContains('discovery_profiles', $tables);
    }

    // ---- data-version -----------------------------------------------------------------

    public function test_data_version_is_validated_by_the_version_itself(): void
    {
        $response = $this->getJson($this->url('data-version'))->assertOk();
        $version = $response->json('version');

        $this->assertSame('W/"'.$version.'"', $response->headers->get('ETag'));
        $cache = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=0', $cache);
        $this->assertStringContainsString('s-maxage=20', $cache);
        $this->assertStringNotContainsString('no-store', $cache);

        $again = $this->getJson($this->url('data-version'), ['If-None-Match' => $response->headers->get('ETag')]);
        $again->assertStatus(304);
        $this->assertSame('', $again->getContent());
    }

    public function test_data_version_changes_when_the_schedule_does_and_an_old_validator_gets_the_new_one(): void
    {
        $old = $this->getJson($this->url('data-version'));

        EventData::put($this->event->id, 'sessions', [
            ['id' => 1, 'title' => 'New keynote', 'starts_at' => '2026-10-10T09:00:00+00:00', 'duration_seconds' => 3600],
        ]);

        $new = $this->getJson($this->url('data-version'), ['If-None-Match' => $old->headers->get('ETag')]);

        $new->assertOk();
        $this->assertNotSame($old->json('version'), $new->json('version'));
    }

    public function test_any_of_several_validators_matches_and_star_matches_everything(): void
    {
        $etag = $this->getJson($this->url('data-version'))->headers->get('ETag');

        $this->getJson($this->url('data-version'), ['If-None-Match' => 'W/"nope", '.$etag])->assertStatus(304);
        $this->getJson($this->url('data-version'), ['If-None-Match' => '"'.trim($etag, 'W/"').'"'])->assertStatus(304); // strong form of the same tag
        $this->getJson($this->url('data-version'), ['If-None-Match' => '*'])->assertStatus(304);
        $this->getJson($this->url('data-version'), ['If-None-Match' => 'W/"something-else"'])->assertOk();
    }

    // ---- What must never be shared or held ------------------------------------------------

    public function test_the_admin_purge_signal_is_never_cached(): void
    {
        $cache = $this->getJson('/api/v1/cache-version')->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringNotContainsString('public', $cache);
    }

    public function test_a_persons_own_waves_are_never_shared(): void
    {
        $card = $this->join();

        $cache = $this->withToken($card['owner_token'])
            ->getJson($this->url("discovery/{$card['discovery_id']}/waves"))->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringNotContainsString('public', $cache);
        $this->assertStringNotContainsString('s-maxage', $cache);
    }

    public function test_an_event_that_is_not_public_still_answers_404_and_nothing_is_built_for_it(): void
    {
        $this->event->update(['status' => 'archived']);

        $this->getJson($this->url('roster'))->assertNotFound();
        $this->getJson($this->url('discovery'))->assertNotFound();
        $this->getJson($this->url('data-version'))->assertNotFound();
    }
}
