<?php

namespace Tests\Feature;

use App\Jobs\SendAppUpdatePushJob;
use App\Models\AppRelease;
use App\Models\Event;
use App\Models\NativePushToken;
use App\Models\User;
use App\Support\ApkInspector;
use App\Support\AppReleases;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Android app updates outside the Play Store (Admin → Mobile app → App updates): the APK is read
 * on the server, refused when a phone would refuse it, uploaded in pieces, published, offered by
 * /api/v1/app/update, served for download and announced with a silent push.
 *
 * The fixtures in tests/Fixtures/apk are tiny real APKs made with aapt2 and apksigner (key "a"
 * and, for one, key "b").
 */
class AppReleaseTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_A = 'ab5d96f340f13a18567646264c448748747b35367ed5d074149dfce9beb7954c';

    private array $history = [];

    private ?string $credentialsFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        config(['mobile_app.app_id' => 'club.campbuddy.app', 'mobile_app.android_sha256' => []]);
    }

    protected function tearDown(): void
    {
        if ($this->credentialsFile) {
            @unlink($this->credentialsFile);
        }
        parent::tearDown();
    }

    private function fixture(string $name): string
    {
        return base_path("tests/Fixtures/apk/{$name}.apk");
    }

    /** Uploads a fixture the way the admin page does: in pieces, then the checksum. */
    private function upload(User $admin, string $name, array $fields = [], int $piece = 3000)
    {
        $bytes = file_get_contents($this->fixture($name));
        $id = (string) Str::uuid();
        foreach (str_split($bytes, $piece) as $index => $chunk) {
            $this->actingAs($admin)->call('POST', route('admin.mobile.releases.chunk'), [], [], [], [
                'HTTP_X_UPLOAD_ID' => $id, 'HTTP_X_CHUNK_INDEX' => (string) $index, 'CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json',
            ], $chunk)->assertOk()->assertJson(['next' => $index + 1]);
        }

        return $this->actingAs($admin)->postJson(route('admin.mobile.releases.store'), ['upload' => $id, 'sha256' => hash('sha256', $bytes)] + $fields);
    }

    public function test_the_apk_is_read_without_android_tools(): void
    {
        $this->assertSame([
            'package' => 'club.campbuddy.app',
            'version_code' => 13,
            'version_name' => '0.0.13',
            'min_sdk' => 24,
            'cert_sha256' => self::KEY_A,
        ], ApkInspector::inspect($this->fixture('campbuddy-13')));

        $this->assertNotSame(self::KEY_A, ApkInspector::inspect($this->fixture('campbuddy-15-other-key'))['cert_sha256']);

        foreach (['campbuddy-16-unsigned' => 'not signed', 'README' => 'not an APK'] as $name => $reason) {
            try {
                ApkInspector::inspect($name === 'README' ? base_path('README.md') : $this->fixture($name));
                $this->fail("{$name} was accepted");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($reason, $e->getMessage());
            }
        }
    }

    public function test_an_upload_in_pieces_becomes_a_checked_draft(): void
    {
        $admin = User::factory()->create();

        $this->upload($admin, 'campbuddy-13', ['notes_en' => 'Faster <b>schedule</b>', 'notes_hi' => 'तेज़ शेड्यूल', 'required' => true])
            ->assertOk()->assertJson(['redirect' => route('admin.mobile.index', ['tab' => 'updates'])]);

        $release = AppRelease::sole();
        $this->assertSame(['club.campbuddy.app', 13, '0.0.13', 'draft', true], [$release->package, $release->version_code, $release->version_name, $release->status, $release->required]);
        $this->assertSame('Faster schedule', $release->notes_en);
        $this->assertSame('तेज़ शेड्यूल', $release->notes_hi);
        $this->assertSame(hash_file('sha256', $this->fixture('campbuddy-13')), $release->sha256);
        $this->assertSame(self::KEY_A, $release->cert_sha256);
        $this->assertSame($admin->id, $release->uploaded_by);
        Storage::disk('local')->assertExists($release->file);
        $this->assertSame([], glob(Storage::disk('local')->path('app-releases/tmp').'/*') ?: [], 'the pieces are cleaned up');
        $this->assertStringContainsString('saved as a draft', session('status'));
    }

    public function test_a_piece_sent_twice_is_kept_once_and_a_gap_is_refused(): void
    {
        $id = (string) Str::uuid();
        $this->assertSame(1, AppReleases::receiveChunk($id, 0, 'abc'));
        $this->assertSame(1, AppReleases::receiveChunk($id, 0, 'abc'), 'a retry after a lost answer');
        $this->assertSame(2, AppReleases::receiveChunk($id, 1, 'def'));
        $this->assertSame('abcdef', file_get_contents(Storage::disk('local')->path("app-releases/tmp/{$id}.part")));

        $this->expectException(InvalidArgumentException::class);
        AppReleases::receiveChunk($id, 5, 'x');
    }

    public function test_a_damaged_upload_or_a_bad_id_is_refused(): void
    {
        $admin = User::factory()->create();
        $id = (string) Str::uuid();
        $this->actingAs($admin)->call('POST', route('admin.mobile.releases.chunk'), [], [], [], ['HTTP_X_UPLOAD_ID' => $id, 'HTTP_X_CHUNK_INDEX' => '0', 'HTTP_ACCEPT' => 'application/json'], file_get_contents($this->fixture('campbuddy-13')))->assertOk();

        $this->actingAs($admin)->postJson(route('admin.mobile.releases.store'), ['upload' => $id, 'sha256' => str_repeat('0', 64)])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'damaged on the way'));
        $this->assertSame(0, AppRelease::count());

        $this->actingAs($admin)->call('POST', route('admin.mobile.releases.chunk'), [], [], [], ['HTTP_X_UPLOAD_ID' => '../../etc/passwd', 'HTTP_X_CHUNK_INDEX' => '0', 'HTTP_ACCEPT' => 'application/json'], 'x')
            ->assertStatus(422);
    }

    public function test_what_a_phone_would_refuse_is_refused_with_the_reason(): void
    {
        $admin = User::factory()->create();

        $this->upload($admin, 'other-app')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'not CampBuddy'));
        $this->upload($admin, 'campbuddy-16-unsigned')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'not signed'));

        $this->upload($admin, 'campbuddy-14')->assertOk();
        $this->upload($admin, 'campbuddy-13')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'not newer than 0.0.14'));
        $this->upload($admin, 'campbuddy-15-other-key')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'different key'));

        // The debug build's id is only accepted on a development machine.
        $this->app['env'] = 'production';
        try {
            AppReleases::assertAcceptable(ApkInspector::inspect($this->fixture('campbuddy-debug-12')));
            $this->fail('the debug build was accepted on the live site');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('debug build', $e->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->upload($admin, 'campbuddy-debug-12')->assertOk();

        $this->assertSame([14, 12], AppRelease::orderByDesc('version_code')->pluck('version_code')->all());
    }

    public function test_the_known_signing_keys_are_enforced_when_set(): void
    {
        $admin = User::factory()->create();
        config(['mobile_app.android_sha256' => ['AA:BB']]);
        $this->upload($admin, 'campbuddy-13')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'MOBILE_ANDROID_SHA256'));

        config(['mobile_app.android_sha256' => ['AA:BB', strtoupper(implode(':', str_split(self::KEY_A, 2)))]]);
        $this->upload($admin, 'campbuddy-13')->assertOk();
    }

    public function test_publishing_offers_the_update_and_queues_the_push(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $this->upload($admin, 'campbuddy-13', ['required' => true])->assertOk();
        $release = AppRelease::sole();

        $this->getJson('/api/v1/app/update?code=12')->assertOk()->assertJson(['update' => false, 'latest' => null, 'required_code' => 0]);

        $this->actingAs($admin)->post(route('admin.mobile.releases.publish', $release))->assertRedirect(route('admin.mobile.index', ['tab' => 'updates']));
        Queue::assertPushed(SendAppUpdatePushJob::class, fn ($job) => $job->releaseId === $release->id);

        $this->getJson('/api/v1/app/update?package=club.campbuddy.app&code=12')->assertOk()
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertJson([
                'package' => 'club.campbuddy.app',
                'update' => true,
                'required_code' => 13,
                'latest' => [
                    'version_code' => 13,
                    'version_name' => '0.0.13',
                    'sha256' => $release->sha256,
                    'cert_sha256' => self::KEY_A,
                    'size' => filesize($this->fixture('campbuddy-13')),
                    'url' => url('/api/v1/app/releases/club.campbuddy.app/13.apk'),
                    'required' => true,
                ],
            ]);
        $this->getJson('/api/v1/app/update?code=13')->assertJson(['update' => false]);
        // An unknown package is answered for the store app, never echoed back.
        $this->getJson('/api/v1/app/update?package=com.evil&code=1')->assertJson(['package' => 'club.campbuddy.app', 'update' => true]);
    }

    public function test_the_live_file_downloads_and_resumes(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $this->upload($admin, 'campbuddy-13')->assertOk();
        $release = AppRelease::sole();
        $url = '/api/v1/app/releases/club.campbuddy.app/13.apk';

        $this->get($url)->assertNotFound(); // a draft is not public
        $this->get('/app/android')->assertNotFound();

        AppReleases::publish($release, $admin);
        $response = $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->assertSame(file_get_contents($this->fixture('campbuddy-13')), $response->streamedContent());
        $this->assertStringContainsString('CampBuddy-0.0.13.apk', $response->headers->get('Content-Disposition'));

        $partial = $this->get($url, ['Range' => 'bytes=100-199'])->assertStatus(206);
        $this->assertSame(substr(file_get_contents($this->fixture('campbuddy-13')), 100, 100), $partial->streamedContent());

        $this->get('/app/android')->assertRedirect(url($url));
        $this->get('/api/v1/app/releases/club.campbuddy.app/99.apk')->assertNotFound();
    }

    public function test_withdraw_and_delete(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $this->upload($admin, 'campbuddy-13')->assertOk();
        $this->upload($admin, 'campbuddy-14')->assertOk();
        [$old, $new] = [AppRelease::where('version_code', 13)->sole(), AppRelease::where('version_code', 14)->sole()];
        AppReleases::publish($old, $admin);
        AppReleases::publish($new, $admin);
        $this->getJson('/api/v1/app/update?code=1')->assertJsonPath('latest.version_code', 14);

        $this->actingAs($admin)->delete(route('admin.mobile.releases.destroy', $new))->assertStatus(409);
        $this->actingAs($admin)->post(route('admin.mobile.releases.withdraw', $new))->assertRedirect();
        $this->getJson('/api/v1/app/update?code=1')->assertJsonPath('latest.version_code', 13);
        $this->get('/api/v1/app/releases/club.campbuddy.app/14.apk')->assertNotFound();

        $this->actingAs($admin)->delete(route('admin.mobile.releases.destroy', $new))->assertRedirect();
        $this->assertModelMissing($new);
        Storage::disk('local')->assertMissing($new->file);
    }

    public function test_the_push_is_silent_and_goes_once_to_each_older_android_phone(): void
    {
        $this->fcm([new Response(200, [], '{}'), new Response(200, [], '{}')]);
        $one = $this->event('wordcamp-one');
        $two = $this->event('wordcamp-two');
        $token = fn (Event $e, string $device, string $platform, ?string $version, string $t) => NativePushToken::create(['event_id' => $e->id, 'device_id' => $device, 'platform' => $platform, 'token' => $t, 'app_version' => $version]);
        $token($one, 'phone-a', 'android', '0.0.12', 'tok-a-old');
        $token($two, 'phone-a', 'android', '0.0.12', 'tok-a-new');
        $token($one, 'phone-b', 'android', '0.0.13', 'tok-b');
        $token($one, 'phone-c', 'android', null, 'tok-c');
        $token($one, 'phone-d', 'ios', '0.0.1', 'tok-d');

        $admin = User::factory()->create();
        Queue::fake();
        $this->upload($admin, 'campbuddy-13')->assertOk();
        $release = AppRelease::sole();
        AppReleases::publish($release, $admin);
        Queue::assertPushed(SendAppUpdatePushJob::class);

        (new SendAppUpdatePushJob($release->id))->handle();

        $sends = collect($this->history)->filter(fn ($h) => str_contains((string) $h['request']->getUri(), 'messages:send'))->values();
        $this->assertCount(2, $sends);
        $bodies = $sends->map(fn ($h) => json_decode((string) $h['request']->getBody(), true)['message']);
        $this->assertEqualsCanonicalizing(['tok-a-new', 'tok-c'], $bodies->pluck('token')->all());
        foreach ($bodies as $message) {
            $this->assertArrayNotHasKey('notification', $message, 'nothing is shown');
            $this->assertArrayNotHasKey('apns', $message);
            $this->assertSame(['version_code' => '13', 'required' => '0', 'kind' => 'app-update'], $message['data']);
            $this->assertSame('HIGH', $message['android']['priority']);
        }
        $this->assertSame(2, $release->fresh()->pushed_to);
    }

    public function test_the_admin_tab_lists_versions_and_phones(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $this->upload($admin, 'campbuddy-13', ['notes_en' => 'Faster schedule'])->assertOk();
        AppReleases::publish(AppRelease::sole(), $admin);
        $event = $this->event('wordcamp-one');
        NativePushToken::create(['event_id' => $event->id, 'device_id' => 'p1', 'platform' => 'android', 'token' => 't1', 'app_version' => '0.0.13']);
        NativePushToken::create(['event_id' => $event->id, 'device_id' => 'p2', 'platform' => 'android', 'token' => 't2', 'app_version' => '0.0.11']);

        $this->actingAs($admin)->get(route('admin.mobile.index', ['tab' => 'updates']))->assertOk()
            ->assertSee('App updates')
            ->assertSee('Upload a new version')
            ->assertSee('data-app-release-upload', false)
            ->assertSee('0.0.13 (build 13)')
            ->assertSee('Faster schedule')
            ->assertSee('0.0.11 · 1 phone')
            ->assertSee(route('app.android'));
    }

    public function test_the_website_footer_offers_the_apk_only_while_a_version_is_live(): void
    {
        Queue::fake();
        Cache::flush();
        foreach (['/', '/guide', '/privacy', '/terms'] as $page) {
            $this->get($page)->assertOk()->assertDontSee('Android app (APK)');
        }

        $admin = User::factory()->create();
        $this->upload($admin, 'campbuddy-13')->assertOk();
        $this->get('/')->assertDontSee('Android app (APK)'); // a draft is not offered
        AppReleases::publish(AppRelease::sole(), $admin);
        foreach (['/', '/guide', '/privacy', '/terms'] as $page) {
            $this->get($page)->assertOk()->assertSee('Android app (APK)')->assertSee(route('app.android'), false);
        }

        AppReleases::withdraw(AppRelease::sole());
        $this->get('/')->assertDontSee('Android app (APK)');
    }

    public function test_guests_cannot_upload_or_publish(): void
    {
        $this->call('POST', route('admin.mobile.releases.chunk'), [], [], [], ['HTTP_X_UPLOAD_ID' => (string) Str::uuid(), 'HTTP_X_CHUNK_INDEX' => '0'], 'x')->assertRedirect();
        $this->postJson(route('admin.mobile.releases.store'), [])->assertUnauthorized();
        $this->assertSame([], glob(Storage::disk('local')->path('app-releases/tmp').'/*') ?: []);
    }

    private function event(string $slug): Event
    {
        return Event::withoutEvents(fn () => Event::create([
            'slug' => $slug, 'display_name' => $slug, 'source_site_url' => "https://{$slug}.wordcamp.org/2026", 'status' => 'active',
            'is_visible' => true, 'starts_on' => '2026-11-21', 'ends_on' => '2026-11-22', 'timezone' => 'Asia/Kolkata', 'country_code' => 'IN',
        ]));
    }

    private function fcm(array $responses): void
    {
        $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        $key = @openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + (file_exists($cnf) ? ['config' => $cnf] : []));
        $pem = null;
        if ($key) {
            @openssl_pkey_export($key, $pem, null, file_exists($cnf) ? ['config' => $cnf] : []);
        }
        if (! $pem) {
            $this->markTestSkipped('OpenSSL cannot make a key on this machine.');
        }
        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->credentialsFile, json_encode(['project_id' => 'campbuddy-test', 'client_email' => 'push@campbuddy-test.iam.gserviceaccount.com', 'private_key' => $pem]));

        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode(['access_token' => 'ya29.test', 'expires_in' => 3599])), ...$responses]));
        $stack->push(Middleware::history($this->history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
        Cache::forget('fcm.access_token');
        config(['services.fcm' => ['credentials_path' => $this->credentialsFile, 'project_id' => null], 'mobile_app.features.native_push' => true]);
    }
}
