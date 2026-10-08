<?php

namespace Tests\Feature;

use App\Jobs\EvaluateEventLifecycleJob;
use App\Models\Event;
use App\Models\FetchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The nightly auto-publish sweep checks a draft's Attendees page itself: a
 * site without one is skipped quietly (no warning, no failed fetch row),
 * while a draft with public attendees still goes live.
 */
class LifecycleDraftRosterTest extends TestCase
{
    use RefreshDatabase;

    private function draft(string $slug): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug,
            'display_name' => ucwords(str_replace('-', ' ', $slug)),
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'draft',
            'is_visible' => true,
            'starts_on' => now()->addDays(30)->toDateString(),
            'ends_on' => now()->addDays(30)->toDateString(),
            'timezone' => 'Asia/Kolkata',
        ]));
    }

    public function test_a_draft_without_an_attendees_page_is_skipped_quietly(): void
    {
        Http::fake([
            'no-page.wordcamp.org/2026/attendees/' => Http::response('Not found', 404),
            'no-page.wordcamp.org/*' => Http::response('<html>site</html>'),
        ]);
        Log::spy();

        $event = $this->draft('no-page');
        (new EvaluateEventLifecycleJob)->handle();

        $this->assertSame('draft', $event->fresh()->status);
        $this->assertSame(0, FetchLog::where('event_id', $event->id)->count());
        Log::shouldNotHaveReceived('warning');
    }

    public function test_a_draft_with_public_attendees_still_goes_live(): void
    {
        $html = '<ul class="tix-attendee-list"><li><span class="tix-attendee-name"><span class="tix-first">Asha</span> <span class="tix-last">Rao</span></span></li></ul>';
        Http::fake(['with-page.wordcamp.org/2026/attendees/' => Http::response($html)]);

        $event = $this->draft('with-page');
        (new EvaluateEventLifecycleJob)->handle();

        $this->assertSame('active', $event->fresh()->status);
    }
}
