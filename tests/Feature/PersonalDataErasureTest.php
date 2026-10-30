<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Support\PersonalDataErasure;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DPDPA s.8(7): what attendees shared is really deleted once the purpose is served — 60 days after the
 * retention window (about 90 days after the event), never before.
 */
class PersonalDataErasureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function event(string $slug, string $endsOn): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug,
            'display_name' => $slug,
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active',
            'is_visible' => true,
            'starts_on' => $endsOn,
            'ends_on' => $endsOn,
            'timezone' => 'UTC',
        ]));
    }

    private function shareSomething(Event $event, string $tag): void
    {
        DB::table('discovery_profiles')->insert(['discovery_id' => "d-{$tag}", 'owner_token_hash' => str_repeat('a', 64), 'event_id' => $event->id, 'fields' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('push_subscriptions')->insert(['event_id' => $event->id, 'device_id' => "dev-{$tag}", 'endpoint' => "https://push.example/{$tag}", 'p256dh_key' => 'k', 'auth_key' => 'a', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('native_push_tokens')->insert(['event_id' => $event->id, 'device_id' => "dev-{$tag}", 'platform' => 'android', 'token' => "tok-{$tag}", 'created_at' => now(), 'updated_at' => now()]);
        DB::table('session_bookmarks')->insert(['event_id' => $event->id, 'device_id' => "dev-{$tag}", 'session_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('event_device_visits')->insert(['event_id' => $event->id, 'device_id' => "dev-{$tag}", 'platform' => 'web', 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    public function test_it_erases_only_events_past_retention_plus_sixty_days(): void
    {
        Carbon::setTestNow('2026-12-31 12:00:00');
        $old = $this->event('wc-old', '2026-09-25');       // ended 97 days ago: retention (30) + 60 passed
        $recent = $this->event('wc-recent', '2026-11-20'); // ended 41 days ago: still in retention
        $this->shareSomething($old, 'old');
        $this->shareSomething($recent, 'recent');

        $this->assertTrue(PersonalDataErasure::due($old));
        $this->assertFalse(PersonalDataErasure::due($recent));

        $dry = PersonalDataErasure::run(dryRun: true);
        $this->assertSame(1, $dry['discovery_profiles']);
        $this->assertSame(2, DB::table('discovery_profiles')->count(), 'a dry run removes nothing');

        $this->artisan('campbuddy:erase-expired-data')->assertSuccessful();

        foreach (['discovery_profiles', 'push_subscriptions', 'native_push_tokens', 'session_bookmarks', 'event_device_visits'] as $table) {
            $this->assertSame(0, DB::table($table)->where('event_id', $old->id)->count(), "{$table} of the old event is gone");
            $this->assertSame(1, DB::table($table)->where('event_id', $recent->id)->count(), "{$table} of the recent event stays");
        }
        $this->assertNotNull(Event::find($old->id), 'the event itself is not personal data and stays');
    }

    public function test_an_event_without_dates_is_never_erased(): void
    {
        Carbon::setTestNow('2030-01-01');
        $event = Event::withoutEvents(fn () => Event::create(['slug' => 'wc-undated', 'display_name' => 'Undated', 'source_site_url' => 'https://u.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]));
        $this->shareSomething($event, 'u');

        $this->assertFalse(PersonalDataErasure::due($event));
        PersonalDataErasure::run();
        $this->assertSame(1, DB::table('discovery_profiles')->where('event_id', $event->id)->count());
    }

    public function test_the_job_is_scheduled_nightly(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('erase-expired-personal-data')->assertSuccessful();
    }

    public function test_free_steal_suggestions_and_sponsor_deal_forms_are_erased_too(): void
    {
        // The bug this guards: both hold a person's name and email (a sponsor form also a phone and IP
        // address) but were never in the erasure list, so they were kept for ever.
        \Illuminate\Support\Facades\Storage::fake('public');
        Carbon::setTestNow('2026-12-31 12:00:00');
        $old = $this->event('wc-old', '2026-09-25');
        $recent = $this->event('wc-recent', '2026-11-20');

        foreach ([$old, $recent] as $event) {
            DB::table('free_steal_suggestions')->insert(['event_id' => $event->id, 'name' => 'Tool', 'url' => 'https://tool.example', 'email' => 'me@example.com', 'created_at' => now(), 'updated_at' => now()]);
        }
        \Illuminate\Support\Facades\Storage::disk('public')->put('sponsor-deals/logos/kept.png', 'png');
        \Illuminate\Support\Facades\Storage::disk('public')->put('sponsor-deals/logos/gone.png', 'png');
        DB::table('media_assets')->insert(['disk' => 'public', 'path' => 'sponsor-deals/logos/kept.png', 'filename' => 'kept.png', 'mime_type' => 'image/png', 'size' => 3, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['kept.png', 'gone.png'] as $logo) {
            DB::table('sponsor_deal_submissions')->insert(['event_id' => $old->id, 'sponsor_name' => 'S', 'title' => 'T', 'description' => 'D', 'url' => 'https://s.example', 'submitter_name' => 'Asha', 'submitter_email' => 'asha@example.com', 'submitter_phone' => '+91 98765 43210', 'ip_address' => '203.0.113.9', 'logo_path' => "sponsor-deals/logos/{$logo}", 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        }

        $removed = PersonalDataErasure::run();

        $this->assertSame(1, $removed['free_steal_suggestions']);
        $this->assertSame(2, $removed['sponsor_deal_submissions']);
        $this->assertSame(0, DB::table('free_steal_suggestions')->where('event_id', $old->id)->count());
        $this->assertSame(1, DB::table('free_steal_suggestions')->where('event_id', $recent->id)->count(), 'not before its time');
        $this->assertSame(0, DB::table('sponsor_deal_submissions')->count());
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('sponsor-deals/logos/gone.png');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('sponsor-deals/logos/kept.png'); // an approved deal still shows this logo
    }
}
