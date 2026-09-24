<?php

namespace Tests\Feature;

use App\Console\Commands\IndexNowCommand;
use App\Models\Event;
use App\Support\EventData;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Search engines (SEO), answer engines (AEO) and AI assistants (GEO): every
 * public page describes itself, the crawl files are built from live data,
 * and nothing private is offered for indexing.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function event(array $overrides = []): Event
    {
        return Event::withoutEvents(fn () => Event::create($overrides + [
            'slug' => 'wc-test',
            'display_name' => 'WordCamp Test 2026',
            'short_name' => 'WCTest',
            'source_site_url' => 'https://test.wordcamp.org/2026',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-02',
            'status' => 'active',
            'is_visible' => true,
            'info' => ['venue' => 'Town Hall, Main Street'],
        ]));
    }

    /** @return array<int, array<string, mixed>> the page's JSON-LD @graph */
    private function graph(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m, 'No JSON-LD on the page');
        $data = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $data['@context']);

        return $data['@graph'];
    }

    private function types(array $graph): array
    {
        return array_column($graph, '@type');
    }

    public function test_the_picker_describes_the_site_the_app_and_its_faq(): void
    {
        $this->event();

        $html = $this->get('/')->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('property="og:image" content="'.url(Seo::IMAGE).'"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('content="index, follow, max-image-preview:large"', false)
            ->getContent();

        $graph = $this->graph($html);
        $this->assertEqualsCanonicalizing(['WebSite', 'WebApplication', 'FAQPage', 'ItemList'], $this->types($graph));
        $this->assertFileExists(public_path(Seo::IMAGE));

        $list = collect($graph)->firstWhere('@type', 'ItemList');
        $this->assertSame('WordCamp Test 2026', $list['itemListElement'][0]['item']['name']);
        $this->assertSame('2026-10-01', $list['itemListElement'][0]['item']['startDate']);
    }

    public function test_the_guide_is_an_article_with_faq_and_glossary(): void
    {
        $html = $this->get(route('guide'))->assertOk()->getContent();

        $this->assertEqualsCanonicalizing(['Article', 'FAQPage', 'DefinedTermSet', 'BreadcrumbList'], $this->types($this->graph($html)));
    }

    public function test_the_schedule_page_lists_its_sessions_as_sub_events(): void
    {
        $event = $this->event();
        EventData::put($event->id, 'speakers', [['id' => 7, 'name' => 'Ada Speaker']]);
        EventData::put($event->id, 'sessions', [
            ['id' => 1, 'title' => 'SEO Basics </script><script>alert(1)</script>', 'starts_at' => '2026-10-01T10:00:00+00:00', 'duration_seconds' => 1800, 'track_names' => ['Room A'], 'speaker_ids' => [7]],
            ['id' => 2, 'title' => 'No time yet', 'starts_at' => null],
        ]);

        $html = $this->get(route('event.my-day', $event))->assertOk()->getContent();

        // A hostile title can't break out of the JSON-LD block.
        $this->assertStringNotContainsString('</script><script>alert(1)', $html);

        $node = collect($this->graph($html))->firstWhere('@type', 'Event');
        $this->assertSame('Town Hall, Main Street', $node['location']['name']);
        $this->assertCount(1, $node['subEvent']);
        $this->assertSame('Room A', $node['subEvent'][0]['location']['name']);
        $this->assertSame('Ada Speaker', $node['subEvent'][0]['performer'][0]['name']);
        $this->assertArrayHasKey('endDate', $node['subEvent'][0]);
    }

    public function test_event_pages_describe_themselves_and_personal_tools_stay_out_of_search(): void
    {
        $event = $this->event();

        foreach (['event.home', 'event.my-day', 'event.explore', 'event.contribute', 'event.guide'] as $route) {
            $this->get(route($route, $event))->assertOk()
                ->assertSee('content="index, follow, max-image-preview:large"', false)
                ->assertSee('<link rel="canonical" href="'.route($route, $event).'">', false)
                ->assertSee('WordCamp Test 2026', false);
        }

        foreach (['event.quest', 'event.camp-card', 'event.roster-removal.show'] as $route) {
            $this->get(route($route, $event))->assertOk()->assertSee('content="noindex, follow"', false);
        }
    }

    public function test_the_sitemap_lists_public_pages_only(): void
    {
        $event = $this->event();
        $this->event(['slug' => 'wc-hidden', 'is_visible' => false]);
        $this->event(['slug' => 'wc-draft', 'status' => 'draft']);

        $xml = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('<loc>'.route('home').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('guide').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('event.my-day', $event).'</loc>', $xml);
        $this->assertStringNotContainsString('camp-card', $xml);
        $this->assertStringNotContainsString('wc-hidden', $xml);
        $this->assertStringNotContainsString('wc-draft', $xml);
    }

    public function test_a_new_event_appears_in_the_sitemap_straight_away(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('wc-new');

        Event::create(['slug' => 'wc-new', 'display_name' => 'WordCamp New', 'source_site_url' => 'https://new.wordcamp.org/2026', 'status' => 'draft', 'is_visible' => true])
            ->update(['status' => 'active']);

        $this->get('/sitemap.xml')->assertOk()->assertSee('wc-new');
    }

    public function test_robots_points_to_the_sitemap_in_production_and_blocks_everything_elsewhere(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);

        $this->app['env'] = 'production';

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap: '.route('sitemap'))
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /event/*/roster-removal')
            ->assertDontSee("Disallow: /\n", false);
    }

    public function test_llms_txt_summarises_campbuddy_and_its_events(): void
    {
        $event = $this->event();

        $this->get('/llms.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# CampBuddy')
            ->assertSee('[WordCamp Test 2026]('.route('event.home', $event).')', false)
            ->assertSee('What is CampBuddy?');
    }

    public function test_the_indexnow_key_file_only_answers_to_the_real_key(): void
    {
        $key = Seo::indexNowKey();

        $this->get("/indexnow-{$key}.txt")->assertOk()->assertSeeText($key);
        $this->get('/indexnow-0000000000000000.txt')->assertNotFound();
    }

    public function test_indexnow_submits_everything_first_then_only_what_changed(): void
    {
        $this->app['env'] = 'production';
        $event = $this->event();
        Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);

        $this->artisan('campbuddy:indexnow')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r['key'] === Seo::indexNowKey()
            && str_contains($r['keyLocation'], '/indexnow-')
            && in_array(route('event.my-day', $event), $r['urlList'], true)
            && in_array(route('guide'), $r['urlList'], true));
        $this->assertNotNull(Cache::get(IndexNowCommand::LAST_RUN_KEY));

        // Nothing changed since: nothing sent.
        Http::fake();
        $this->artisan('campbuddy:indexnow')->expectsOutputToContain('Nothing changed')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_indexnow_never_runs_outside_production(): void
    {
        Http::fake();

        $this->artisan('campbuddy:indexnow')->expectsOutputToContain('Skipped')->assertSuccessful();

        Http::assertNothingSent();
    }
}
