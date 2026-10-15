<?php

namespace Tests\Feature;

use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\OrganizerPushToken;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Admins and event managers in the store app: their push tokens, the alert
 * when a profile is reported, and the manager's own Reports tab.
 */
class OrganizerAppTest extends TestCase
{
    use RefreshDatabase;

    private const FALLBACK_TEST_KEY = "-----BEGIN PRIVATE KEY-----\n"
        ."MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDC7879UmKgzX2a\n"
        ."EqSRzm0UIptXh26VIbzuAXiMnCFeXfBo3q1Nc3DC+rQz46zd6f5Vz91k4BRzHo/t\n"
        ."GhO6IrXlzSejo9fmvNs+lCDBRSMefZV1M4kFKp2m8DIyBeFAQbZ10hh2Gi/ExD0n\n"
        ."5SuO8ZRZMB5gO1l9TuD30o1RI7CJqY+U8LLPAs2yM9Bu9jTYmTRCqO1Qh7zZpY/0\n"
        ."0e0sbw8EGyrYdrSeMW7xsVYnboN8p2KSX/wkDV6GnpKhazjQjXqH2sr2glJCW3LS\n"
        ."tTiHQ+CISSdDVJOy7cd0eo6M24ZoaWJKzgPEnSYLGkQEWVwDdLWzTKKcp66h4JMM\n"
        ."4UWp6zi1AgMBAAECggEAAL3LPnzl+aEWcXPRMunDUfCIwTY3ZjNM5eXVCMneOZ7n\n"
        ."V2rDY3hFLUSpU0oyIdzuNfLWr3fHtCFhYEA5Y3uW0AMt+6oXm93LgqsrDbke11KY\n"
        ."cyvpQT8GfvhKZJTPpMArOrr8E1Tpqhip9+Ef/4jtLMKpVuna3wZiBidDmNOnK/ra\n"
        ."qMNF2W9pE0qeljzzlFkav3t2F5DEu/Jfm0bfrWRCcK4xbJDBNiBsJejx/8NJ3pL5\n"
        ."CbCQlFO4jgFWr6xVNLANTrJXOqBpN9HdwWkiulLwbEEXYriyB+nUfENaqJmZDQr2\n"
        ."SB7M/5WtU7/k4cvUx0PlMovjwDiQ23yr5w7tBS7oYQKBgQDpgIrfB22PmklEKjF/\n"
        ."j4g1eFMEBdqFMVISDZ+vXIVRLsqyOi2NqXC4Tf8w3OZHxyalq8Bc2woEuxuMmnjZ\n"
        ."cf6RCfWkFM/lZX5fCreSFppgaFO9pc5wWJGmNcNNi9o6QBMRvtDxSioq2OYfnE4L\n"
        ."zGbVYVuN1KSFCisRnBIq/GzD1QKBgQDVuAe78W0s8G4jpxJwrUZOCxVit28HKeJ1\n"
        ."Rnpw5Ma8nG5n62PUJB4nBSw0e8sdS1ys0G+WJxFWe0CUhcWP4LuYWpDANQDmVbdB\n"
        ."M7B6D25yUT5yy2cRAq/w4GOgkdEgXLR5CC5AC1gcZRLiOJRaIU6e50rm9mtUrP3F\n"
        ."gWsjzzRxYQKBgQDJHJt/aKhmrN45xZWIZplMZGwr5NbHLSmgvvcwVKHGwGR4iT8+\n"
        ."6hdZxmPrM/drDw4sVveBgm7okDc0LL+1mUdGqKtXPU2qKHZlaZeeTbV9H54wk6Rw\n"
        ."gvg/jcK/AZtd3F4QRKc1i0kySko6I6fc5ZoUYpw2VlW0+IML0b6cI3mSWQKBgHJd\n"
        ."XlYmZWPM7UNK3kVJk/ujVrhSrKlKspJLOq463laD51jYz7zv7uq2i+cx74knkNox\n"
        ."V+Uj1tVOc0qHIydQ/VIT9K3GL0sSx1Ou01sd8t5dt2s6iVbC91+Q0/9SyHPm5/R0\n"
        ."ejtvVqErPSWtXUW12RqTbrOVXe4b/Fi6Xpj1An1BAoGAHq7a/5Vk65aAQfOFPzPQ\n"
        ."7bwypjVzPeib9ezylV6YpWq1aaxw4sadiYKQCHi8YDfu5f/dpUzZme2XcUHx3ECI\n"
        ."RKNDKduQyBYteiBRyTlsFqUe47R/L3k/qzmxzlUR2fmMwhWT/6Bx2toXofgTPiOO\n"
        ."Q6MOS1dxY9GPFRk9BwhevDo=\n"
        ."-----END PRIVATE KEY-----\n";

    private const TOKEN = 'org-token-AAAAAAAAAAAAAAAAAAAA:APA91bExample_token-1234567890';

    private array $history = [];

    private ?string $credentialsFile = null;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Bus::fake();
        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wordcamp-test', 'display_name' => 'WordCamp Test', 'source_site_url' => 'https://wordcamp-test.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true, 'starts_on' => now()->addDays(2)->toDateString(), 'ends_on' => now()->addDays(3)->toDateString(),
            'timezone' => 'Asia/Kolkata',
        ]));
    }

    protected function tearDown(): void
    {
        if ($this->credentialsFile) {
            @unlink($this->credentialsFile);
        }
        parent::tearDown();
    }

    private function fcm(int $sends): void
    {
        $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        $configArgs = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if (file_exists($cnf)) {
            $configArgs['config'] = $cnf;
        }

        $pem = null;
        $key = @openssl_pkey_new($configArgs);
        if ($key) {
            @openssl_pkey_export($key, $pem, null, file_exists($cnf) ? ['config' => $cnf] : []);
        }

        if (! $pem) {
            $pem = self::FALLBACK_TEST_KEY;
        }

        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->credentialsFile, json_encode(['project_id' => 'campbuddy-test', 'client_email' => 'push@test.iam.gserviceaccount.com', 'private_key' => $pem]));

        $queue = [new Response(200, [], json_encode(['access_token' => 'ya29.test']))];
        for ($i = 0; $i < $sends; $i++) {
            $queue[] = new Response(200, [], '{}');
        }
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
        Cache::forget('fcm.access_token');
        config(['services.fcm' => ['credentials_path' => $this->credentialsFile, 'project_id' => null]]);
    }

    private function manager(bool $assigned = true, array $attributes = []): EventManager
    {
        $manager = EventManager::factory()->create($attributes);
        if ($assigned) {
            $manager->events()->attach($this->event->id);
        }

        return $manager;
    }

    private function signInManager(EventManager $manager): void
    {
        Auth::guard('manager')->setUser($manager);
    }

    private function profile(): DiscoveryProfile
    {
        $c = DiscoveryProfile::generateCredentials();

        return DiscoveryProfile::create([
            'discovery_id' => $c['discovery_id'], 'owner_token_hash' => $c['owner_token_hash'], 'event_id' => $this->event->id,
            'fields' => ['tags' => ['seo'], 'display_name' => 'Spammy Sam'], 'expires_at' => now()->addWeek(),
        ]);
    }

    public function test_an_admin_registers_and_removes_a_phone(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->postJson('/admin/app-push', ['platform' => 'ios', 'token' => self::TOKEN, 'app_version' => '1.0.0'])->assertCreated();
        $this->assertSame(['admin', $admin->id], [OrganizerPushToken::first()->role, (int) OrganizerPushToken::first()->user_id]);

        $this->actingAs($admin)->deleteJson('/admin/app-push', ['token' => self::TOKEN])->assertNoContent();
        $this->assertSame(0, OrganizerPushToken::count());
    }

    public function test_a_manager_registers_a_phone_and_one_person_cannot_remove_anothers(): void
    {
        $mine = $this->manager();
        $other = $this->manager();

        $this->signInManager($mine);
        $this->postJson('/manager/app-push', ['platform' => 'android', 'token' => self::TOKEN])->assertCreated();
        $this->assertSame('manager', OrganizerPushToken::first()->role);

        $this->signInManager($other);
        $this->deleteJson('/manager/app-push', ['token' => self::TOKEN])->assertNoContent();
        $this->assertSame(1, OrganizerPushToken::count(), 'someone else cannot unregister it');
    }

    public function test_a_guest_cannot_register_and_bad_tokens_are_refused(): void
    {
        $this->postJson('/admin/app-push', ['platform' => 'ios', 'token' => self::TOKEN])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/admin/app-push', ['platform' => 'ios', 'token' => 'x y'])->assertUnprocessable();
        $this->assertSame(0, OrganizerPushToken::count());
    }

    public function test_a_report_alerts_admins_and_the_events_managers_only(): void
    {
        $admin = User::factory()->create();
        $mine = $this->manager();
        $notMine = $this->manager(false);
        $inactive = $this->manager(true, ['is_active' => false]);
        foreach ([['admin', $admin->id, 'a'], ['manager', $mine->id, 'm'], ['manager', $notMine->id, 'n'], ['manager', $inactive->id, 'i']] as [$role, $id, $x]) {
            OrganizerPushToken::create(['role' => $role, 'user_id' => $id, 'platform' => 'android', 'token' => 'tok-'.str_repeat($x, 30)]);
        }
        $this->fcm(2);
        $profile = $this->profile();

        $this->postJson("/api/v1/events/{$this->event->slug}/discovery/{$profile->discovery_id}/report", ['device_id' => 'd1', 'reason' => 'spam'])->assertCreated();

        $sends = collect($this->history)->filter(fn ($h) => str_contains((string) $h['request']->getUri(), 'messages:send'));
        $this->assertCount(2, $sends, 'the admin and the assigned active manager');
        $urls = $sends->map(fn ($h) => json_decode((string) $h['request']->getBody(), true)['message']['data']['url'])->sort()->values()->all();
        $this->assertSame(["/admin/events/{$this->event->id}/reports", "/manager/events/{$this->event->id}/reports"], $urls);
    }

    public function test_a_pile_on_is_one_alert_an_hour(): void
    {
        OrganizerPushToken::create(['role' => 'admin', 'user_id' => User::factory()->create()->id, 'platform' => 'ios', 'token' => 'tok-'.str_repeat('a', 30)]);
        $this->fcm(1);
        $profile = $this->profile();
        $url = "/api/v1/events/{$this->event->slug}/discovery/{$profile->discovery_id}/report";

        $this->postJson($url, ['device_id' => 'd1', 'reason' => 'spam'])->assertCreated();
        $this->postJson($url, ['device_id' => 'd2', 'reason' => 'abusive'])->assertCreated();

        $this->assertSame(1, collect($this->history)->filter(fn ($h) => str_contains((string) $h['request']->getUri(), 'messages:send'))->count());
        $this->assertSame(2, DiscoveryReport::count());
    }

    public function test_a_manager_sees_and_handles_reports_for_their_own_event_only(): void
    {
        $manager = $this->manager();
        $stranger = $this->manager(false);
        $profile = $this->profile();
        DiscoveryReport::create(['event_id' => $this->event->id, 'discovery_id' => $profile->discovery_id, 'reporter_device_id' => 'd1', 'reason' => 'spam', 'snapshot' => ['name' => 'Spammy Sam'], 'status' => 'open']);

        $this->signInManager($manager);
        $this->get("/manager/events/{$this->event->id}/reports")->assertOk()->assertSee('Spammy Sam');
        $this->post("/manager/events/{$this->event->id}/reports/{$profile->discovery_id}/remove")->assertRedirect();
        $this->assertNull(DiscoveryProfile::find($profile->id));
        $this->assertSame('removed', DiscoveryReport::first()->status);

        $this->signInManager($stranger);
        $this->get("/manager/events/{$this->event->id}/reports")->assertNotFound();
    }
}
