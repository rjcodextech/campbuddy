<?php

namespace Tests\Feature;

use App\Support\MobileAppConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Versions start again at 0.0.1: a 0.0.1 build must not open on "Please update CampBuddy". */
class MobileAppMinimumVersionTest extends TestCase
{
    use RefreshDatabase;

    private function publish(array $minApp, int $version = 3): void
    {
        DB::table('mobile_app_configs')->insert([
            'version' => $version, 'status' => 'published', 'json' => json_encode(['min_app' => $minApp, 'flags' => ['native_push' => true]]),
            'schema' => 1, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::flush();
    }

    private function migrate(): void
    {
        (require database_path('migrations/2026_10_22_090000_lower_mobile_app_minimum_to_0_0_1.php'))->up();
    }

    public function test_the_defaults_let_a_0_0_1_build_in(): void
    {
        $this->getJson('/api/v1/app/config')->assertOk()->assertJsonPath('min_app.android', '0.0.1')->assertJsonPath('min_app.ios', '0.0.1');
    }

    public function test_the_old_default_on_the_live_config_is_lowered_as_a_new_version(): void
    {
        $this->publish(['android' => '1.0.0', 'ios' => '1.0.0']);
        $this->migrate();

        $this->assertSame(4, MobileAppConfig::liveVersion(), 'a new version, so phones fetch it');
        $this->assertSame('archived', DB::table('mobile_app_configs')->where('version', 3)->value('status'));
        $this->getJson('/api/v1/app/config')->assertOk()->assertJsonPath('min_app.android', '0.0.1')->assertJsonPath('flags.native_push', true);
    }

    public function test_a_minimum_an_admin_chose_is_left_alone(): void
    {
        $this->publish(['android' => '1.2.0', 'ios' => '1.0.0']);
        $this->migrate();

        $this->getJson('/api/v1/app/config')->assertOk()->assertJsonPath('min_app.android', '1.2.0')->assertJsonPath('min_app.ios', '0.0.1');
        $this->migrate();
        $this->assertSame(4, MobileAppConfig::liveVersion(), 'running it again changes nothing');
    }
}
