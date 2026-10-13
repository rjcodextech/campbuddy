<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LineIcons;
use App\Support\MobileAppConfig;
use App\Support\MobileAppDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The native app's server-driven settings: the defaults are valid, the API
 * serves them, and the admin editor can change them safely (draft, validate,
 * publish, roll back).
 */
class MobileAppConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** One text of the served config (the keys contain dots, so they are not JSON paths). */
    private function text(string $locale, string $key): ?string
    {
        return $this->getJson('/api/v1/app/config')->json("strings.{$locale}")[$key] ?? null;
    }

    public function test_the_built_in_defaults_are_a_valid_config(): void
    {
        $this->assertSame([], MobileAppConfig::validate(MobileAppDefaults::config()));
    }

    public function test_every_default_icon_text_and_language_is_complete(): void
    {
        $config = MobileAppDefaults::config();
        foreach (array_merge($config['tabs'], $config['organizer']['tabs']) as $tab) {
            $this->assertTrue(LineIcons::has($tab['icon']) || in_array($tab['icon'], ['home', 'dashboard'], true), "icon {$tab['icon']}");
            $this->assertArrayHasKey($tab['title'], $config['strings']['en']);
        }
        $this->assertSame(array_keys($config['strings']['en']), array_keys($config['strings']['hi']), 'Hindi has every text English has');
    }

    public function test_the_schema_file_lists_the_same_choices_as_the_server(): void
    {
        $schema = json_decode(file_get_contents(base_path('schema/app-config.schema.json')), true);
        $defs = $schema['definitions'];

        $this->assertSame(MobileAppDefaults::TAB_KEYS, $defs['tabKey']['enum']);
        $this->assertSame(MobileAppDefaults::SECTION_TYPES, $defs['sectionType']['enum']);
        $this->assertSame(MobileAppDefaults::ORGANIZER_TAB_KEYS, $defs['organizerTabKey']['enum']);
        $this->assertSame(MobileAppDefaults::FLAGS, array_keys($schema['properties']['flags']['properties']));
        $this->assertSame(MobileAppDefaults::VISIBLE_IF, $defs['visibleIf']['enum']);
        $this->assertSame(MobileAppDefaults::SCHEMA, $schema['properties']['schema']['const']);
    }

    public function test_the_api_serves_the_defaults_until_something_is_published(): void
    {
        $this->getJson('/api/v1/app/config')
            ->assertOk()
            ->assertJsonPath('version', 0)
            ->assertJsonPath('schema', 1)
            ->assertJsonPath('tabs.0.key', 'home')
            ->assertJsonPath('strings.hi', MobileAppDefaults::strings('hi'))
            ->assertHeader('Cache-Control', 'max-age=60, public');
    }

    public function test_the_api_answers_304_when_the_app_already_has_this_version(): void
    {
        $etag = $this->getJson('/api/v1/app/config')->headers->get('ETag');

        $this->getJson('/api/v1/app/config', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_only_admins_reach_the_editor(): void
    {
        $this->get('/admin/mobile-app')->assertRedirect();
        $this->post('/admin/mobile-app/publish')->assertRedirect();

        $this->actingAs($this->admin())->get('/admin/mobile-app')->assertOk()->assertSee('Mobile app')->assertSee('Overview');
        foreach (['navigation', 'home', 'text', 'look', 'banner', 'switches', 'history'] as $tab) {
            $this->actingAs($this->admin())->get('/admin/mobile-app?tab='.$tab)->assertOk();
        }
    }

    public function test_an_edit_goes_to_a_draft_and_is_live_only_after_publishing(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/mobile-app/text', ['strings' => ['en' => ['picker.choose' => 'Pick your WordCamp'], 'hi' => ['picker.choose' => 'अपना कैंप चुनें']]])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertTrue(MobileAppConfig::hasDraft());
        $this->assertSame('Choose your WordCamp', $this->text('en', 'picker.choose'));

        $this->actingAs($admin)->post('/admin/mobile-app/publish', ['note' => 'Wording'])->assertRedirect()->assertSessionHas('status');
        Cache::flush();

        $this->getJson('/api/v1/app/config')->assertJsonPath('version', 1);
        $this->assertSame('Pick your WordCamp', $this->text('en', 'picker.choose'));
        $this->assertSame('अपना कैंप चुनें', $this->text('hi', 'picker.choose'));
        $this->assertFalse(MobileAppConfig::hasDraft());
    }

    public function test_a_change_that_would_break_the_app_is_refused_and_not_saved(): void
    {
        $admin = $this->admin();

        // Text nearly the same colour as the background.
        $this->actingAs($admin)->post('/admin/mobile-app/look', ['primary' => '#60121C', 'accent' => '#DA7F01', 'background' => '#FCF1DB', 'ink' => '#FAF0D8', 'muted' => '#6E5A54', 'radius' => 12, 'splash_text' => 'x'])
            ->assertSessionHas('config_errors');
        // Fewer than two tabs.
        $tabs = collect(MobileAppDefaults::config()['tabs'])->mapWithKeys(fn ($t) => [$t['key'] => ['order' => $t['order'], 'icon' => $t['icon']]])->all();
        $this->actingAs($admin)->post('/admin/mobile-app/navigation', ['tabs' => array_merge($tabs, ['home' => ['order' => 1, 'icon' => 'home', 'enabled' => '1']])])
            ->assertSessionHas('config_errors');
        // A banner link that is not https.
        $this->actingAs($admin)->post('/admin/mobile-app/banner', ['banner_on' => '1', 'text_en' => 'Hi', 'style' => 'info', 'action_type' => 'url', 'action_url' => 'javascript:alert(1)'])
            ->assertSessionHas('config_errors');
        // HTML in a text.
        $this->actingAs($admin)->post('/admin/mobile-app/text', ['strings' => ['en' => ['picker.choose' => '<b>Pick</b>']]])->assertSessionHas('config_errors');

        $this->assertFalse(MobileAppConfig::hasDraft(), 'nothing invalid was saved');
    }

    public function test_the_editor_cannot_add_a_text_the_app_does_not_use(): void
    {
        $config = MobileAppDefaults::config();
        $config['strings']['en']['made.up'] = 'Hello';

        $this->assertContains('Text "made.up" (en) is not one the app uses.', MobileAppConfig::validate($config));
    }

    public function test_a_banner_shows_until_its_end_time_and_then_disappears(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/mobile-app/banner', ['banner_on' => '1', 'text_en' => 'WordCamp Delhi is live!', 'text_hi' => 'WordCamp Delhi शुरू!', 'style' => 'success', 'action_type' => 'event', 'action_slug' => 'wordcamp-delhi-2026', 'until' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionHas('status');
        $this->actingAs($admin)->post('/admin/mobile-app/publish');
        Cache::flush();

        $this->getJson('/api/v1/app/config')->assertJsonPath('banner.text.en', 'WordCamp Delhi is live!')->assertJsonPath('banner.action.slug', 'wordcamp-delhi-2026');

        $this->travel(2)->days();
        Cache::flush();
        $this->getJson('/api/v1/app/config')->assertJsonPath('banner', null);
    }

    public function test_navigation_order_and_switches_follow_the_form(): void
    {
        $admin = $this->admin();
        $rows = [];
        foreach (MobileAppDefaults::config()['tabs'] as $tab) {
            $rows[$tab['key']] = ['order' => 10 - $tab['order'], 'icon' => $tab['icon'], 'enabled' => '1'];
        }
        $this->actingAs($admin)->post('/admin/mobile-app/navigation', ['tabs' => $rows])->assertSessionHas('status');
        $this->actingAs($admin)->post('/admin/mobile-app/switches', ['flags' => ['native_push' => '1'], 'min_app' => ['android' => '1.2.0', 'ios' => '1.0.0']])->assertSessionHas('status');
        $this->actingAs($admin)->post('/admin/mobile-app/publish');
        Cache::flush();

        $response = $this->getJson('/api/v1/app/config');
        $response->assertJsonPath('tabs.0.key', 'memories')->assertJsonPath('flags.native_push', true)->assertJsonPath('flags.discovery', false)->assertJsonPath('min_app.android', '1.2.0');
    }

    public function test_rolling_back_publishes_an_older_version_again(): void
    {
        $admin = $this->admin();
        foreach (['First wording', 'Second wording'] as $text) {
            $this->actingAs($admin)->post('/admin/mobile-app/text', ['strings' => ['en' => ['picker.choose' => $text]]]);
            $this->actingAs($admin)->post('/admin/mobile-app/publish');
        }
        Cache::flush();
        $this->getJson('/api/v1/app/config')->assertJsonPath('version', 2);
        $this->assertSame('Second wording', $this->text('en', 'picker.choose'));

        $this->actingAs($admin)->post('/admin/mobile-app/rollback/1')->assertSessionHas('status');
        Cache::flush();

        $this->getJson('/api/v1/app/config')->assertJsonPath('version', 3);
        $this->assertSame('First wording', $this->text('en', 'picker.choose'));
        $this->assertCount(3, MobileAppConfig::history());
    }

    public function test_every_change_is_written_to_the_audit_trail(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/mobile-app/text', ['strings' => ['en' => ['picker.choose' => 'Pick']]]);
        $this->actingAs($admin)->post('/admin/mobile-app/publish');

        $this->assertSame(['draft', 'publish'], \DB::table('mobile_app_config_audit')->orderBy('id')->pluck('action')->all());
        $this->assertSame($admin->id, (int) \DB::table('mobile_app_config_audit')->value('user_id'));
    }
}
