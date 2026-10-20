<?php

use App\Models\User;
use App\Support\MobileAppConfig;
use App\Support\MobileAppDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed and publish the initial default mobile app configuration.
     * Ensures /admin/mobile-app shows live published settings and /api/v1/app/config
     * delivers the complete default settings in sync.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (DB::table('mobile_app_configs')->where('status', 'published')->exists()) {
            return;
        }

        $admin = User::first();
        $config = MobileAppDefaults::config();

        // 1. Ensure draft is created with full default configuration
        MobileAppConfig::saveDraft($config, $admin, 'all');

        // 2. Publish version 1 live
        MobileAppConfig::publish($admin, 'Initial default settings published live');

        Cache::forget('mobile-app-config:published');
    }

    public function down(): void
    {
        DB::table('mobile_app_configs')->where('version', 1)->delete();
        DB::table('mobile_app_config_audit')->where('version', 1)->delete();
        Cache::forget('mobile-app-config:published');
    }
};
