<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Social media page's kept copies (storage/app/social-cache) older than a
 * day are removed every night at 00:15; newer ones stay.
 */
class SocialCacheCleanupTest extends TestCase
{
    private function copy(string $name, int $hoursOld): void
    {
        $disk = Storage::disk('local');
        $disk->put("social-cache/{$name}", str_repeat('x', 1000));
        $disk->put("social-cache/{$name}.type", 'image/png');
        $at = now()->subHours($hoursOld)->getTimestamp();
        touch($disk->path("social-cache/{$name}"), $at);
        touch($disk->path("social-cache/{$name}.type"), $at);
    }

    public function test_copies_older_than_a_day_go_and_newer_ones_stay(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $this->copy('old', 30);
        $this->copy('fresh', 2);
        $disk->put('social-cache/orphan.type', 'image/png');

        $this->artisan('campbuddy:clear-social-cache --dry-run')->expectsOutputToContain('Would remove 1 cached file(s)')->assertSuccessful();
        $disk->assertExists('social-cache/old');

        $this->artisan('campbuddy:clear-social-cache')->expectsOutputToContain('Removed 1 cached file(s)')->assertSuccessful();
        $disk->assertMissing('social-cache/old');
        $disk->assertMissing('social-cache/old.type');
        $disk->assertMissing('social-cache/orphan.type');
        $disk->assertExists('social-cache/fresh');
        $disk->assertExists('social-cache/fresh.type');
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
        $this->artisan('campbuddy:clear-social-cache')->expectsOutputToContain('Removed 0 cached file(s)')->assertSuccessful();
    }
}
