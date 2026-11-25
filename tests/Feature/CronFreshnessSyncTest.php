<?php

namespace Tests\Feature;

use App\Models\AttendeeRoster;
use App\Models\ContributorTable;
use App\Models\Event;
use App\Support\CatalogVersion;
use App\Support\ContributorTables;
use App\Support\DataVersion;
use App\Support\ScheduleDelay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CronFreshnessSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_events_version_endpoint_and_home_meta_update_when_event_catalog_changes(): void
    {
        $v1 = CatalogVersion::current();

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="campbuddy-events-version" content="'.$v1.'">', false);

        $first = $this->getJson('/api/v1/events-version')
            ->assertOk()
            ->assertExactJson(['version' => $v1]);

        $this->getJson('/api/v1/events-version', ['If-None-Match' => $first->headers->get('ETag')])
            ->assertStatus(304);

        Event::create([
            'slug' => 'wc-cron-test',
            'display_name' => 'WordCamp Cron Test',
            'source_site_url' => 'https://cron-test.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
        ]);

        $v2 = CatalogVersion::current();
        $this->assertNotSame($v1, $v2);

        $this->getJson('/api/v1/events-version')
            ->assertOk()
            ->assertExactJson(['version' => $v2]);
    }

    public function test_data_version_reflects_roster_photos_contributor_tables_and_delays(): void
    {
        $event = Event::create([
            'slug' => 'wc-sync-event',
            'display_name' => 'WordCamp Sync Event',
            'source_site_url' => 'https://sync.wordcamp.org/2026',
            'status' => 'active',
            'is_visible' => true,
            'timezone' => 'Asia/Kolkata',
        ]);

        $v0 = DataVersion::for($event->fresh());

        // 1. Roster ingest change
        $entry = AttendeeRoster::create([
            'event_id' => $event->id,
            'content_hash' => hash('sha256', 'attendee-1'),
            'name' => 'Asha Verma',
            'is_suppressed' => false,
        ]);
        DataVersion::forget($event->id);
        $v1 = DataVersion::for($event->fresh());
        $this->assertNotSame($v0, $v1);

        // 2. FillMissingPhotosJob style quiet photo update
        $entry->forceFill(['photo_url' => 'https://secure.gravatar.com/avatar/abc?s=600&d=404', 'photo_source' => 'gravatar'])->saveQuietly();
        DataVersion::forget($event->id);
        $v2 = DataVersion::for($event->fresh());
        $this->assertNotSame($v1, $v2);

        // 3. Contributor Day table added by cron
        ContributorTable::create([
            'event_id' => $event->id,
            'team' => 'core',
            'title' => null,
            'leads' => [['name' => 'Asha Verma']],
            'sort_order' => 100,
        ]);
        ContributorTables::changed($event);
        $v3 = DataVersion::for($event->fresh());
        $this->assertNotSame($v2, $v3);

        // 4. Running late delay set & cleared
        ScheduleDelay::set($event->fresh(), null, 15, CarbonImmutable::now(), 'Lunch ran long', 'admin@example.com');
        $v4 = DataVersion::for($event->fresh());
        $this->assertNotSame($v3, $v4);
    }
}
