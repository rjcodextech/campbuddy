<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The apps' versions start again at 0.0.1 (21 Oct 2026), but the live app
 * config still asked for at least 1.0.0 (its old default), so a 0.0.1 build
 * opened on "Please update CampBuddy". Where the published config (and a
 * waiting draft) still asks for exactly 1.0.0 — the untouched default — it
 * becomes 0.0.1, published as a new version so every app fetches it (an
 * unchanged version number would keep the saved copy on the phone). A
 * minimum an admin chose themselves is left as it is.
 */
return new class extends Migration
{
    private const OLD = '1.0.0';

    private const NEW = '0.0.1';

    public function up(): void
    {
        if (! Schema::hasTable('mobile_app_configs')) {
            return;
        }
        $lower = function (array $config): ?array {
            $changed = false;
            foreach (['android', 'ios'] as $platform) {
                if (($config['min_app'][$platform] ?? null) === self::OLD) {
                    $config['min_app'][$platform] = self::NEW;
                    $changed = true;
                }
            }

            return $changed ? $config : null;
        };
        $json = fn (array $c) => json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        DB::transaction(function () use ($lower, $json) {
            $draft = DB::table('mobile_app_configs')->where('status', 'draft')->first();
            if ($draft && ($c = $lower(json_decode($draft->json, true) ?: []))) {
                DB::table('mobile_app_configs')->where('id', $draft->id)->update(['json' => $json($c), 'updated_at' => now()]);
            }

            $live = DB::table('mobile_app_configs')->where('status', 'published')->first();
            if (! $live || ! ($c = $lower(json_decode($live->json, true) ?: []))) {
                return;
            }
            $version = (int) DB::table('mobile_app_configs')->max('version') + 1;
            DB::table('mobile_app_configs')->where('id', $live->id)->update(['status' => 'archived', 'updated_at' => now()]);
            DB::table('mobile_app_configs')->insert([
                'version' => $version, 'status' => 'published', 'json' => $json($c), 'schema' => $live->schema,
                'note' => 'Lowest app version 0.0.1 (versions start again at 0.0.1)', 'created_by' => $live->created_by,
                'published_by' => null, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (Schema::hasTable('mobile_app_config_audit')) {
                DB::table('mobile_app_config_audit')->insert(['user_id' => null, 'action' => 'publish', 'version' => $version, 'section' => 'switches', 'diff' => json_encode(['min_app']), 'created_at' => now()]);
            }
        });
        Cache::forget('mobile-app-config:published');
    }

    public function down(): void
    {
        // The older version stays in the history: roll back to it from Admin → Mobile app if needed.
    }
};
