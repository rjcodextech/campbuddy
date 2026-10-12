<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Support\EventData;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Social media page's copies (storage/app/private/social-cache): every
 * night at 00:15 only the ones no listed event uses any more, and older than
 * a day, are removed; copies in use stay however old.
 */
class SocialCacheCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function copy(string $name, int $hoursOld): void
    {
        $disk = Storage::disk('local');
        $disk->put("social-cache/{$name}", str_repeat('x', 1000));
        $disk->put("social-cache/{$name}.type", 'image/png');
        $at = now()->subHours($hoursOld)->getTimestamp();
        touch($disk->path("social-cache/{$name}"), $at);
        touch($disk->path("social-cache/{$name}.type"), $at);
    }

    public function test_only_old_copies_no_listed_event_uses_go(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $logo = 'https://jaipur.wordcamp.org/2026/files/2026/09/sponsor-logo.png';
        $art = 'https://jaipur.wordcamp.org/2026/files/2026/09/jaipur-skyline.png';
        $event = Event::create(['slug' => 'wordcamp-jaipur-2026', 'display_name' => 'WordCamp Jaipur 2026', 'source_site_url' => 'https://jaipur.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        EventData::put($event->id, 'sponsors', [['name' => 'Acme', 'logo_url' => $logo]]);
        $event->forceFill(['site_theme' => ['fetched_at' => now()->toIso8601String(), 'art' => [['url' => $art, 'kind' => 'illustration', 'name' => 'jaipur-skyline']]]])->save();
        $archived = Event::create(['slug' => 'wordcamp-old-2025', 'display_name' => 'Old', 'source_site_url' => 'https://old.wordcamp.org/2025', 'status' => 'archived', 'is_visible' => true]);
        EventData::put($archived->id, 'sponsors', [['name' => 'Gone', 'logo_url' => 'https://old.wordcamp.org/2025/files/gone.png']]);

        $this->copy(sha1($logo), 300);
        $this->copy(sha1($art), 300);
        $this->copy(sha1('https://old.wordcamp.org/2025/files/gone.png'), 30);
        $this->copy(sha1('https://jaipur.wordcamp.org/2026/files/2026/09/replaced-artwork.png'), 30);
        $this->copy('fresh-but-unused', 2);
        $disk->put('social-cache/orphan.type', 'image/png');

        $this->artisan('campbuddy:clear-social-cache --dry-run')->expectsOutputToContain('Would remove 2 unused file(s)')->assertSuccessful();
        $disk->assertExists('social-cache/'.sha1('https://old.wordcamp.org/2025/files/gone.png'));

        $this->artisan('campbuddy:clear-social-cache')->expectsOutputToContain('Removed 2 unused file(s)')->assertSuccessful();
        // A sponsor logo and artwork still in use stay, however old.
        $disk->assertExists('social-cache/'.sha1($logo));
        $disk->assertExists('social-cache/'.sha1($art).'.type');
        // An archived event's copy, and artwork the site replaced, go.
        $disk->assertMissing('social-cache/'.sha1('https://old.wordcamp.org/2025/files/gone.png'));
        $disk->assertMissing('social-cache/'.sha1('https://jaipur.wordcamp.org/2026/files/2026/09/replaced-artwork.png'));
        // Made in the last day: a page may be using it right now.
        $disk->assertExists('social-cache/fresh-but-unused');
        $disk->assertMissing('social-cache/orphan.type');
    }

    public function test_it_runs_every_night_at_a_quarter_past_midnight(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'clear-social-cache');
        $this->assertNotNull($event);
        $this->assertSame('15 0 * * *', $event->expression);
    }

    public function test_an_empty_or_missing_cache_is_fine(): void
    {
        Storage::fake('local');
        $this->artisan('campbuddy:clear-social-cache')->expectsOutputToContain('Removed 0 unused file(s)')->assertSuccessful();
    }
}
