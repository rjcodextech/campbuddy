<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\UsagePost;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Analytics → Usage post: "CampBuddy at {WordCamp}" from the event's real
 * numbers, 3 days before it to 3 days after it; small numbers left off.
 */
class UsagePostTest extends TestCase
{
    use RefreshDatabase;

    private string $keyFile;

    private array $asked = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00'));

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->keyFile = tempnam(sys_get_temp_dir(), 'ga');
        file_put_contents($this->keyFile, json_encode(['client_email' => 'reader@test.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['analytics.property_id' => '123', 'analytics.credentials' => $this->keyFile, 'services.google_analytics.measurement_id' => 'G-OURS']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'analyticsadmin.googleapis.com/*' => Http::response(['dataStreams' => [
                ['name' => 'properties/123/dataStreams/222', 'displayName' => 'CampBuddy', 'webStreamData' => ['measurementId' => 'G-OURS', 'defaultUri' => 'https://campbuddy.club']],
            ]]),
            'analyticsdata.googleapis.com/*' => function (Request $request) {
                $this->asked[] = $request->data();
                $reports = array_map(function ($req) {
                    $dims = array_column($req['dimensions'] ?? [], 'name');
                    $metrics = array_column($req['metrics'], 'name');
                    $rows = match ($dims) {
                        [] => [[[], ['totalUsers' => 240, 'screenPageViews' => 3100, 'userEngagementDuration' => 240 * 600]]],
                        ['eventName'] => [
                            [['session_save'], ['eventCount' => 850, 'totalUsers' => 160]],
                            [['quest_complete'], ['eventCount' => 410, 'totalUsers' => 120]],
                            [['discovery_join'], ['eventCount' => 70, 'totalUsers' => 64]],
                            [['data_export'], ['eventCount' => 2, 'totalUsers' => 2]],   // too few: left off
                        ],
                        ['date'] => [[['20261003'], ['totalUsers' => 180]], [['20261004'], ['totalUsers' => 150]]],
                        default => [[['Keynote: The future of WordPress'], ['eventCount' => 40]], [['Block themes'], ['eventCount' => 2]]],
                    };

                    return [
                        'dimensionHeaders' => array_map(fn ($d) => ['name' => $d], $dims),
                        'metricHeaders' => array_map(fn ($m) => ['name' => $m], $metrics),
                        'rows' => array_map(fn ($r) => [
                            'dimensionValues' => array_map(fn ($v) => ['value' => $v], $r[0]),
                            'metricValues' => array_map(fn ($m) => ['value' => (string) ($r[1][$m] ?? 0)], $metrics),
                        ], $rows),
                    ];
                }, $request->data()['requests']);

                return Http::response(['reports' => $reports]);
            },
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyFile);
        parent::tearDown();
    }

    private function event(string $slug = 'wordcamp-jaipur-2026', string $start = '2026-10-03', string $end = '2026-10-04'): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug, 'display_name' => 'WordCamp Jaipur 2026', 'short_name' => '#WCJaipur', 'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active', 'is_visible' => true, 'starts_on' => $start, 'ends_on' => $end, 'timezone' => 'Asia/Kolkata',
        ]));
    }

    public function test_the_range_is_three_days_either_side_and_never_past_today(): void
    {
        $this->assertSame(['from' => '2026-09-30', 'to' => '2026-10-07'], UsagePost::range($this->event()));
        $this->assertSame(['from' => '2026-10-08', 'to' => '2026-10-12'], UsagePost::range($this->event('wc-now', '2026-10-11', '2026-10-12')));
        $this->assertNull(UsagePost::range($this->event('wc-later', '2026-11-20', '2026-11-21')), 'not begun yet');
    }

    public function test_it_builds_the_numbers_card_and_caption_from_real_data_only(): void
    {
        $event = $this->event();
        foreach (['a', 'b', 'c', 'd'] as $d) {
            PushSubscription::create(['event_id' => $event->id, 'device_id' => $d, 'endpoint' => "https://push.example.test/{$d}", 'p256dh_key' => 'k', 'auth_key' => 'a']);
        }
        foreach ([5, 5, 4, 5, 4] as $i => $r) {
            EventFeedback::create(['event_id' => $event->id, 'device_hash' => str_repeat((string) $i, 64), 'rating' => $r]);
        }

        $this->actingAs(User::factory()->create());
        $d = $this->getJson(route('admin.analytics.usage-post', ['event' => $event->slug]))->assertOk()->json();

        $this->assertTrue($d['ready']);
        $this->assertSame(['from' => '2026-09-30', 'to' => '2026-10-07'], $d['range']);
        $this->assertSame(['users', 'pages', 'session_save', 'quest_complete', 'discovery_join', 'reminders'], array_column($d['tiles'], 'key'), 'users first, then the biggest; tiny ones out');
        $this->assertSame(240, $d['tiles'][0]['value']);
        $this->assertSame('people joined Meet people', collect($d['stats'])->firstWhere('key', 'discovery_join')['label']);
        $this->assertSame(4, collect($d['stats'])->firstWhere('key', 'reminders')['value']);
        $this->assertSame(['average' => 4.6, 'count' => 5], $d['rating']);
        $this->assertSame(10.0, (float) $d['minutes']);
        $this->assertCount(8, $d['daily'], 'every day of the range, 0 where nobody came');
        $this->assertSame(180, collect($d['daily'])->firstWhere('date', '2026-10-03')['users']);
        $this->assertSame(['Keynote: The future of WordPress'], array_column($d['talks'], 'title'), 'a talk saved twice is not "most saved"');

        $this->assertStringStartsWith('240 people used CampBuddy at WordCamp Jaipur 2026 (3–4 Oct 2026).', $d['caption']);
        $this->assertStringContainsString('• 850 talks saved', $d['caption']);
        $this->assertStringNotContainsString('PDF', $d['caption'], 'two PDFs is too few to say');
        $this->assertStringContainsString('They rated it 4.6 out of 5 (5 ratings).', $d['caption']);
        $this->assertStringContainsString('#CampBuddy #WPSimplified', $d['caption']);

        // Every GA question is about this event's slug, in the chosen stream, bots left out, in the range.
        $requests = collect($this->asked)->flatMap(fn ($b) => $b['requests'] ?? [])->all();
        $this->assertNotEmpty($requests);
        foreach ($requests as $r) {
            $filters = json_encode($r['dimensionFilter']);
            $this->assertStringContainsString('"customEvent:event_slug","stringFilter":{"value":"wordcamp-jaipur-2026"', $filters);
            $this->assertStringContainsString('screenResolution', $filters);
            $this->assertSame([['startDate' => '2026-09-30', 'endDate' => '2026-10-07']], $r['dateRanges']);
        }
    }

    public function test_a_wordcamp_that_has_not_begun_says_so_and_guests_get_nothing(): void
    {
        $later = $this->event('wc-later', '2026-11-20', '2026-11-21');
        $this->getJson(route('admin.analytics.usage-post', ['event' => $later->slug]))->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->getJson(route('admin.analytics.usage-post', ['event' => $later->slug]))->assertOk()->assertJson(['ready' => false]);
        $this->getJson(route('admin.analytics.usage-post', ['event' => 'nope']))->assertNotFound();
    }

    public function test_the_analytics_page_offers_the_wordcamps_that_have_begun(): void
    {
        $this->event();
        $this->event('wc-later', '2026-11-20', '2026-11-21');
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.analytics.index'))->assertOk()
            ->assertSee('Usage post')->assertSee('data-usage-post', false)
            ->assertSee('<option value="wordcamp-jaipur-2026">', false)
            ->assertDontSee('<option value="wc-later">', false);
    }
}
