<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\AnalyticsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Admin → Analytics reads GA4 through the Data API, one stream at a time.
 * Google is faked here; the page must never break when GA can't be read.
 */
class AdminAnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    private string $keyFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-10-06 10:00:00');

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->keyFile = tempnam(sys_get_temp_dir(), 'ga');
        file_put_contents($this->keyFile, json_encode(['client_email' => 'reader@test.iam.gserviceaccount.com', 'private_key' => $pem]));

        config([
            'analytics.property_id' => '123',
            'analytics.credentials' => $this->keyFile,
            'services.google_analytics.measurement_id' => 'G-OURS',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        @unlink($this->keyFile);

        parent::tearDown();
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'analyticsadmin.googleapis.com/*' => Http::response(['dataStreams' => [
                ['name' => 'properties/123/dataStreams/111', 'displayName' => 'Other site', 'webStreamData' => ['measurementId' => 'G-OTHER', 'defaultUri' => 'https://other.test']],
                ['name' => 'properties/123/dataStreams/222', 'displayName' => 'CampBuddy', 'webStreamData' => ['measurementId' => 'G-OURS', 'defaultUri' => 'https://campbuddy.club']],
            ]]),
            'analyticsdata.googleapis.com/*' => function (Request $request) {
                $reports = array_map(function ($req) {
                    $dims = array_column($req['dimensions'] ?? [], 'name');
                    $metrics = array_column($req['metrics'], 'name');
                    $row = [
                        'dimensionValues' => array_map(fn ($d) => ['value' => match ($d) {
                            'date' => '20261005', 'eventName' => 'session_save', 'customEvent:event_slug' => 'wc-test',
                            'customEvent:metric_name' => 'LCP', 'customEvent:metric_rating' => 'good', 'hour' => '11', 'dayOfWeek' => '0',
                            default => 'Jaipur',
                        }], $dims),
                        'metricValues' => array_map(fn () => ['value' => '7'], $metrics),
                    ];

                    return [
                        'dimensionHeaders' => array_map(fn ($d) => ['name' => $d], $dims),
                        'metricHeaders' => array_map(fn ($m) => ['name' => $m], $metrics),
                        'rows' => [$row],
                    ];
                }, $request['requests']);

                return Http::response(['reports' => $reports]);
            },
        ]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/analytics')->assertRedirect(route('login'));
    }

    public function test_it_shows_the_report_for_our_stream_by_default(): void
    {
        $this->fakeGoogle();
        Event::create(['slug' => 'wc-test', 'display_name' => 'WordCamp Test', 'source_site_url' => 'https://test.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/analytics')
            ->assertOk()
            ->assertSee('CampBuddy (campbuddy.club)')
            ->assertSee('Other site (other.test)')
            ->assertSee('WordCamp Test')
            ->assertSee('Jaipur')
            ->assertSee('26 Sep 2026 – 5 Oct 2026');

        // Every report is filtered to our stream (222), bots left out, last 10 whole days.
        $batches = Http::recorded(fn (Request $r) => str_contains($r->url(), 'batchRunReports'));
        $this->assertCount((int) ceil(count(AnalyticsReport::definitions()) / 5), $batches);
        foreach ($batches as [$request]) {
            foreach ($request['requests'] as $req) {
                $this->assertSame(['startDate' => '2026-09-26', 'endDate' => '2026-10-05'], $req['dateRanges'][0]);
                $filters = json_encode($req['dimensionFilter']);
                $this->assertStringContainsString('"value":"222"', $filters);
                $this->assertStringContainsString('800x600', $filters);
            }
        }
    }

    public function test_stream_dates_and_bots_can_be_chosen(): void
    {
        $this->fakeGoogle();

        $this->actingAs(User::factory()->create())
            ->get('/admin/analytics?stream=111&period=custom&from=2026-10-01&to=2026-10-03')
            ->assertOk();

        [$request] = Http::recorded(fn (Request $r) => str_contains($r->url(), 'batchRunReports'))->first();
        $req = $request['requests'][0];
        $this->assertSame(['startDate' => '2026-10-01', 'endDate' => '2026-10-03'], $req['dateRanges'][0]);
        $this->assertStringContainsString('"value":"111"', json_encode($req['dimensionFilter']));
        // Submitted form without the checkbox = bots included.
        $this->assertStringNotContainsString('800x600', json_encode($req['dimensionFilter']));
    }

    public function test_bad_custom_dates_fall_back_with_a_note(): void
    {
        $this->fakeGoogle();

        $this->actingAs(User::factory()->create())
            ->get('/admin/analytics?period=custom&from=2026-10-09&to=2026-10-01&hide_bots=1')
            ->assertOk()
            ->assertSee('Those dates didn')
            ->assertSee('26 Sep 2026 – 5 Oct 2026');
    }

    public function test_results_are_cached_for_an_hour(): void
    {
        $this->fakeGoogle();
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/analytics')->assertOk();
        $this->actingAs($admin)->get('/admin/analytics')->assertOk();
        $calls = Http::recorded(fn (Request $r) => str_contains($r->url(), 'batchRunReports'))->count();

        $this->assertSame((int) ceil(count(AnalyticsReport::definitions()) / 5), $calls);
    }

    public function test_missing_setup_or_a_google_error_shows_a_message_not_a_crash(): void
    {
        $admin = User::factory()->create();

        config(['analytics.credentials' => '/nowhere/key.json']);
        $this->actingAs($admin)->get('/admin/analytics')->assertOk()->assertSee('Google Analytics couldn')->assertSee('GA_CREDENTIALS_PATH is not set');

        config(['analytics.credentials' => $this->keyFile]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'analyticsadmin.googleapis.com/*' => Http::response(['error' => ['message' => 'Google Analytics Admin API has not been used in project 9 before or it is disabled.']], 403),
        ]);
        $this->actingAs($admin)->get('/admin/analytics')->assertOk()->assertSee('Admin API has not been used');
    }
}
