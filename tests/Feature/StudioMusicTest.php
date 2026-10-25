<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventManager;
use App\Models\User;
use App\Support\StudioMusic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Social media → Reels → Music: the Studio music library CampBuddy shares with
 * WPSimplified (outside the web root), offered only when the folder is there,
 * and served only for the files its tracks.json lists.
 */
class StudioMusicTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private string $studio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'slug' => 'wordcamp-rajasthan-2026', 'display_name' => 'WordCamp Rajasthan 2026', 'short_name' => '#WCRajasthan',
            'source_site_url' => 'https://rajasthan.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true,
            'timezone' => 'Asia/Kolkata', 'starts_on' => '2026-10-03', 'ends_on' => '2026-10-04',
        ]);

        // A small Studio: one groove track with its parts, one plain track, a file nobody listed, a secret next door.
        $this->studio = storage_path('framework/testing/studio-'.uniqid());
        $library = $this->studio.'/Audio/Library';
        File::ensureDirectoryExists($library.'/parts');
        File::put($library.'/wps-groove-sunrise.mp3', 'FULL-GROOVE-AUDIO');
        File::put($library.'/wps-reels-ambient.mp3', 'AMBIENT-AUDIO');
        foreach (['intro', 'bed', 'reel'] as $part) {
            File::put($library."/parts/wps-groove-sunrise-{$part}.mp3", strtoupper($part).'-0123456789');
        }
        File::put($library.'/unlisted.mp3', 'NOT-IN-THE-LIST');
        File::put($this->studio.'/secret.txt', 'SECRET');
        File::put($library.'/tracks.json', json_encode(['tracks' => [
            ['id' => 'reels', 'title' => 'Reels Ambient Groove', 'filename' => 'wps-reels-ambient.mp3', 'duration' => 310],
            ['id' => 'groove', 'title' => 'Sunrise Commit (Afro House)', 'filename' => 'wps-groove-sunrise.mp3', 'duration' => 325, 'parts' => [
                ['id' => 'intro', 'name' => 'Intro sting', 'filename' => 'parts/wps-groove-sunrise-intro.mp3', 'duration' => 15.8],
                ['id' => 'bed', 'name' => 'Groove bed loop', 'filename' => 'parts/wps-groove-sunrise-bed.mp3', 'duration' => 31.5],
                ['id' => 'reel', 'name' => 'Reel cut', 'filename' => 'parts/wps-groove-sunrise-reel.mp3', 'duration' => 33.5],
            ]],
            ['id' => 'gone', 'title' => 'File not there', 'filename' => 'missing.mp3', 'duration' => 300],
            ['id' => 'sneaky', 'title' => 'Escape', 'filename' => '../../secret.txt', 'duration' => 1],
        ]]));
        config(['campbuddy.studio.path' => $this->studio]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->studio);
        parent::tearDown();
    }

    public function test_the_library_lists_reel_cuts_then_loops_then_full_tracks_whose_file_is_there(): void
    {
        $this->assertSame(str_replace('\\', '/', $this->studio), StudioMusic::root());
        $this->assertSame([
            ['file' => 'parts/wps-groove-sunrise-reel.mp3', 'title' => 'Sunrise Commit (Afro House)', 'kind' => 'reel', 'seconds' => 34],
            ['file' => 'parts/wps-groove-sunrise-bed.mp3', 'title' => 'Sunrise Commit (Afro House)', 'kind' => 'loop', 'seconds' => 32],
            ['file' => 'wps-reels-ambient.mp3', 'title' => 'Reels Ambient Groove', 'kind' => 'full', 'seconds' => 310],
            ['file' => 'wps-groove-sunrise.mp3', 'title' => 'Sunrise Commit (Afro House)', 'kind' => 'full', 'seconds' => 325],
        ], StudioMusic::tracks(), 'no intro sting, no missing file, nothing outside the library');
    }

    public function test_the_reels_offer_the_library_and_stream_a_track_with_range_support(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.events.social', $this->event))->assertOk()
            ->assertSee('WPSimplified Studio music')->assertSee('data-reel-track', false)
            ->assertSee('Reel cuts (about 30 s)')->assertSee('Loops (repeat smoothly)')->assertSee('Full tracks (5 min)')
            ->assertSee('Sunrise Commit (Afro House) · 34 s')
            ->assertSee(route('admin.events.social.music', $this->event).'?f=parts%2Fwps-groove-sunrise-reel.mp3', false)
            // The other choices are still there.
            ->assertSee("CampBuddy's music (own per reel)", false)->assertSee('My own track')->assertSee('No music');

        $url = route('admin.events.social.music', $this->event);
        $track = $this->get($url.'?f=parts%2Fwps-groove-sunrise-reel.mp3')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertSame('REEL-0123456789', $track->streamedContent());
        $this->assertStringContainsString('private', $track->headers->get('Cache-Control'));

        $partial = $this->get($url.'?f=parts%2Fwps-groove-sunrise-reel.mp3', ['Range' => 'bytes=5-8'])->assertStatus(206);
        $this->assertSame('0123', $partial->streamedContent());
    }

    public function test_only_files_the_library_lists_are_served(): void
    {
        $this->actingAs(User::factory()->create());
        $url = route('admin.events.social.music', $this->event).'?f=';

        foreach (['unlisted.mp3', 'parts/wps-groove-sunrise-intro.mp3', 'tracks.json', '../../secret.txt', '..%2F..%2Fsecret.txt', 'missing.mp3', ''] as $file) {
            $this->get($url.$file)->assertNotFound();
        }
    }

    public function test_guests_and_other_events_managers_get_nothing(): void
    {
        $this->get(route('admin.events.social.music', $this->event).'?f=wps-reels-ambient.mp3')->assertRedirect();

        $other = Event::create(['slug' => 'wc-other', 'display_name' => 'Other', 'source_site_url' => 'https://o.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $manager = EventManager::factory()->create(['is_active' => true]);
        $manager->events()->attach($this->event->id);
        Auth::guard('manager')->setUser($manager);

        $this->get(route('manager.events.social', $this->event->id))->assertOk()->assertSee('WPSimplified Studio music');
        $this->assertSame('AMBIENT-AUDIO', $this->get(route('manager.events.social.music', $this->event->id).'?f=wps-reels-ambient.mp3')->assertOk()->streamedContent());
        $this->get(route('manager.events.social.music', $other->id).'?f=wps-reels-ambient.mp3')->assertNotFound();
    }

    public function test_without_the_folder_the_option_is_left_out_and_nothing_breaks(): void
    {
        config(['campbuddy.studio.path' => null, 'campbuddy.studio.candidates' => [$this->studio.'/nowhere']]);
        $this->assertNull(StudioMusic::root());
        $this->assertSame([], StudioMusic::tracks());

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.events.social', $this->event))->assertOk()
            ->assertSee('My own track')->assertDontSee('WPSimplified Studio music')->assertDontSee('data-reel-track', false);
        $this->get(route('admin.events.social.music', $this->event).'?f=wps-reels-ambient.mp3')->assertNotFound();

        // No STUDIO_PATH: the first candidate folder that exists is used.
        config(['campbuddy.studio.candidates' => [$this->studio.'/nowhere', $this->studio]]);
        $this->assertSame(str_replace('\\', '/', $this->studio), StudioMusic::root());
    }
}
