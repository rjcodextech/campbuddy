<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The native app's server-driven settings: one draft being edited, and
 * published versions (the newest is live). Everything the app reads goes
 * through here; nothing is published without passing validate(), so a bad edit
 * can never reach a phone. The app also carries MobileAppDefaults as its
 * fallback, so a phone that cannot reach the server still looks right.
 */
class MobileAppConfig
{
    private const CACHE_KEY = 'mobile-app-config:published';

    /** @return array<string, mixed> the live config with its "version" */
    public static function published(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $row = self::row('published');

            if (! $row) {
                return ['version' => 0] + MobileAppDefaults::config();
            }

            return ['version' => (int) $row->version] + self::merged(json_decode($row->json, true) ?: []);
        });
    }

    /** What the editor shows: the draft, else the live config. */
    public static function working(): array
    {
        $draft = self::row('draft');
        $config = $draft ? json_decode($draft->json, true) : null;

        if (is_array($config)) {
            return self::merged($config);
        }

        $live = self::published();
        unset($live['version']);

        return $live;
    }

    public static function hasDraft(): bool
    {
        return self::row('draft') !== null;
    }

    public static function liveVersion(): int
    {
        return (int) (self::row('published')?->version ?? 0);
    }

    /** @return list<array{version: int, note: ?string, published_at: ?string, status: string, by: ?string}> */
    public static function history(): array
    {
        return DB::table('mobile_app_configs')
            ->leftJoin('users', 'users.id', '=', 'mobile_app_configs.published_by')
            ->whereIn('mobile_app_configs.status', ['published', 'archived'])
            ->orderByDesc('mobile_app_configs.version')
            ->limit(30)
            ->get(['mobile_app_configs.version', 'mobile_app_configs.note', 'mobile_app_configs.published_at', 'mobile_app_configs.status', 'users.name as by'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /** Keys the editor knows are filled in from the defaults, so an older document still validates. */
    public static function merged(array $config): array
    {
        $defaults = MobileAppDefaults::config();
        $merged = array_replace_recursive($defaults, array_diff_key($config, array_flip(['tabs', 'home', 'organizer', 'strings', 'banner'])));

        foreach (['tabs'] as $list) {
            $merged[$list] = is_array($config[$list] ?? null) ? $config[$list] : $defaults[$list];
        }
        $merged['home'] = ['sections' => $config['home']['sections'] ?? $defaults['home']['sections']];
        $merged['organizer'] = ['tabs' => $config['organizer']['tabs'] ?? $defaults['organizer']['tabs']];
        $merged['banner'] = array_key_exists('banner', $config) ? $config['banner'] : null;

        foreach (MobileAppDefaults::LOCALES as $locale) {
            $merged['strings'][$locale] = array_replace($defaults['strings'][$locale], $config['strings'][$locale] ?? []);
        }

        return $merged;
    }

    public static function saveDraft(array $config, ?User $user, string $section): void
    {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row = self::row('draft');
        $before = $row ? json_decode($row->json, true) : self::working();

        if ($row) {
            DB::table('mobile_app_configs')->where('id', $row->id)->update(['json' => $json, 'schema' => MobileAppDefaults::SCHEMA, 'updated_at' => now()]);
        } else {
            DB::table('mobile_app_configs')->insert(['status' => 'draft', 'json' => $json, 'schema' => MobileAppDefaults::SCHEMA, 'created_by' => $user?->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        self::audit($user, 'draft', null, $section, self::diff($before, $config));
    }

    public static function discardDraft(?User $user): void
    {
        DB::table('mobile_app_configs')->where('status', 'draft')->delete();
        self::audit($user, 'discard', null, null, null);
    }

    /**
     * Makes the draft the live config. Returns the new version, or throws
     * InvalidArgumentException with the validation errors.
     */
    public static function publish(?User $user, ?string $note = null): int
    {
        $draft = self::row('draft');
        $config = $draft ? json_decode($draft->json, true) : null;

        if (! is_array($config)) {
            throw new \InvalidArgumentException('There is no draft to publish.');
        }

        $errors = self::validate(self::merged($config));
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        return DB::transaction(function () use ($draft, $user, $note) {
            $version = (int) DB::table('mobile_app_configs')->max('version') + 1;
            DB::table('mobile_app_configs')->where('status', 'published')->update(['status' => 'archived']);
            DB::table('mobile_app_configs')->where('id', $draft->id)->update([
                'status' => 'published', 'version' => $version, 'note' => $note ? mb_substr($note, 0, 200) : null,
                'published_by' => $user?->id, 'published_at' => now(), 'updated_at' => now(),
            ]);
            self::audit($user, 'publish', $version, null, null);
            Cache::forget(self::CACHE_KEY);

            return $version;
        });
    }

    /** Publishes an older version again, as a new version. */
    public static function rollback(int $version, ?User $user): int
    {
        $old = DB::table('mobile_app_configs')->where('version', $version)->whereIn('status', ['published', 'archived'])->first();
        if (! $old) {
            throw new \InvalidArgumentException("Version {$version} does not exist.");
        }

        DB::table('mobile_app_configs')->where('status', 'draft')->delete();
        DB::table('mobile_app_configs')->insert(['status' => 'draft', 'json' => $old->json, 'schema' => $old->schema, 'created_by' => $user?->id, 'created_at' => now(), 'updated_at' => now()]);
        $new = self::publish($user, "Rollback to version {$version}");
        self::audit($user, 'rollback', $new, null, ['from' => $version]);

        return $new;
    }

    // ---- validation ----------------------------------------------------------------------

    /** @return list<string> everything wrong with the config (empty = fine to publish) */
    public static function validate(array $c): array
    {
        $e = [];
        $en = $c['strings']['en'] ?? [];

        if (($c['schema'] ?? null) !== MobileAppDefaults::SCHEMA) {
            $e[] = 'schema must be '.MobileAppDefaults::SCHEMA.'.';
        }

        foreach (['android', 'ios'] as $platform) {
            if (! preg_match('/^\d+\.\d+\.\d+$/', (string) ($c['min_app'][$platform] ?? ''))) {
                $e[] = "Minimum {$platform} version must look like 1.2.3.";
            }
        }

        foreach (($c['flags'] ?? []) as $flag => $value) {
            if (! in_array($flag, MobileAppDefaults::FLAGS, true) || ! is_bool($value)) {
                $e[] = "Switch \"{$flag}\" is not a known on/off switch.";
            }
        }

        // Theme: colours, and text that stays readable.
        foreach (['primary', 'accent', 'background', 'surface', 'peach', 'line', 'ink', 'muted', 'danger', 'success'] as $token) {
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($c['theme'][$token] ?? ''))) {
                $e[] = "Colour \"{$token}\" must be like #60121C.";
            }
        }
        if (! $e) {
            if (self::contrast($c['theme']['ink'], $c['theme']['background']) < 4.5) {
                $e[] = 'Text colour on the background is too faint to read (needs contrast 4.5:1).';
            }
            if (self::contrast('#FFFFFF', $c['theme']['primary']) < 4.5) {
                $e[] = 'White text on the main colour is too faint to read (needs contrast 4.5:1).';
            }
        }
        $radius = $c['theme']['radius'] ?? null;
        if (! is_int($radius) || $radius < 0 || $radius > 28) {
            $e[] = 'Corner radius must be a whole number from 0 to 28.';
        }
        if (! empty($c['theme']['logo']) && ! preg_match('#^https://#', (string) $c['theme']['logo'])) {
            $e[] = 'The logo must be an https link.';
        }
        if (mb_strlen((string) ($c['theme']['splash_text'] ?? '')) > 60) {
            $e[] = 'Splash text is too long (60 characters).';
        }

        // Tabs and sections.
        $e = array_merge($e, self::validateList($c['tabs'] ?? [], MobileAppDefaults::TAB_KEYS, 'key', 'tab', $en));
        $e = array_merge($e, self::validateList($c['organizer']['tabs'] ?? [], MobileAppDefaults::ORGANIZER_TAB_KEYS, 'key', 'organizer tab', $en));
        $e = array_merge($e, self::validateList($c['home']['sections'] ?? [], MobileAppDefaults::SECTION_TYPES, 'type', 'home section', $en, true));

        foreach (($c['home']['sections'] ?? []) as $i => $section) {
            $when = $section['visible_if'] ?? null;
            if ($when !== null && ! in_array($when, MobileAppDefaults::VISIBLE_IF, true)) {
                $e[] = 'Home section '.($i + 1).": \"{$when}\" is not a known condition.";
            }
        }
        if (collect($c['tabs'] ?? [])->where('enabled', true)->count() < 2) {
            $e[] = 'Keep at least two tabs switched on.';
        }

        // Banner.
        $banner = $c['banner'] ?? null;
        if ($banner !== null) {
            if (! is_string($banner['text']['en'] ?? null) || trim($banner['text']['en']) === '' || mb_strlen($banner['text']['en']) > 200) {
                $e[] = 'Banner needs English text of up to 200 characters.';
            }
            if (! in_array($banner['style'] ?? 'info', ['info', 'warning', 'success'], true)) {
                $e[] = 'Banner style must be info, warning or success.';
            }
            $action = $banner['action'] ?? null;
            if ($action !== null) {
                if (! in_array($action['type'] ?? null, MobileAppDefaults::ACTION_TYPES, true)) {
                    $e[] = 'Banner button does a thing the app does not know.';
                } elseif ($action['type'] === 'url' && ! preg_match('#^https://[^\s]+$#', (string) ($action['url'] ?? ''))) {
                    $e[] = 'A banner link must be an https address.';
                }
                if (($action['type'] ?? null) === 'event' && ! preg_match('/^[a-z0-9-]{3,120}$/', (string) ($action['slug'] ?? ''))) {
                    $e[] = 'The WordCamp name for the banner button looks like wordcamp-delhi-2026.';
                }
            }
            if (! empty($banner['until']) && strtotime((string) $banner['until']) === false) {
                $e[] = 'Banner end time is not a date.';
            }
        }

        // Strings: only known keys, plain text, sensible length; English is complete.
        $known = array_keys(MobileAppDefaults::strings('en'));
        foreach (MobileAppDefaults::LOCALES as $locale) {
            foreach (($c['strings'][$locale] ?? []) as $key => $value) {
                if (! in_array($key, $known, true)) {
                    $e[] = "Text \"{$key}\" ({$locale}) is not one the app uses.";
                } elseif (! is_string($value) || trim($value) === '') {
                    $e[] = "Text \"{$key}\" ({$locale}) is empty.";
                } elseif (mb_strlen($value) > 300 || $value !== strip_tags($value)) {
                    $e[] = "Text \"{$key}\" ({$locale}) must be plain text up to 300 characters.";
                }
            }
        }
        foreach ($known as $key) {
            if (! isset($en[$key]) || $en[$key] === '') {
                $e[] = "English text \"{$key}\" is missing.";
            }
        }

        return array_values(array_unique($e));
    }

    /** WCAG contrast ratio of two #RRGGBB colours. */
    public static function contrast(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            $channels = array_map(function ($part) {
                $v = hexdec($part) / 255;

                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, str_split(ltrim($hex, '#'), 2));

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        [$hi, $lo] = [max($lum($a), $lum($b)), min($lum($a), $lum($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    // ---- helpers -----------------------------------------------------------------------------

    /** @return list<string> */
    private static function validateList(array $items, array $allowed, string $field, string $label, array $en, bool $titleOptional = false): array
    {
        $e = [];
        $seen = [];

        foreach ($items as $i => $item) {
            $n = $i + 1;
            $name = $item[$field] ?? null;

            if (! in_array($name, $allowed, true)) {
                $e[] = ucfirst($label)." {$n}: \"{$name}\" is not one the app knows.";
                continue;
            }
            if (in_array($name, $seen, true) && $label !== 'home section') {
                $e[] = ucfirst($label)." \"{$name}\" appears twice.";
            }
            $seen[] = $name;

            if (! is_bool($item['enabled'] ?? null)) {
                $e[] = ucfirst($label)." \"{$name}\": enabled must be on or off.";
            }
            if (! is_int($item['order'] ?? null)) {
                $e[] = ucfirst($label)." \"{$name}\": order must be a whole number.";
            }
            if (isset($item['icon']) && ! LineIcons::has($item['icon']) && ! in_array($item['icon'], ['home', 'dashboard'], true)) {
                $e[] = ucfirst($label)." \"{$name}\": icon \"{$item['icon']}\" is not in the icon list.";
            }
            $title = $item['title'] ?? null;
            if ($title === null && ! $titleOptional) {
                $e[] = ucfirst($label)." \"{$name}\" needs a title.";
            } elseif ($title !== null && ! isset($en[$title])) {
                $e[] = ucfirst($label)." \"{$name}\": title text \"{$title}\" does not exist.";
            }
        }

        return $e;
    }

    private static function row(string $status): ?object
    {
        return DB::table('mobile_app_configs')->where('status', $status)->orderByDesc('id')->first();
    }

    private static function audit(?User $user, string $action, ?int $version, ?string $section, ?array $diff): void
    {
        DB::table('mobile_app_config_audit')->insert([
            'user_id' => $user?->id, 'action' => $action, 'version' => $version, 'section' => $section,
            'diff' => $diff === null ? null : json_encode($diff, JSON_UNESCAPED_UNICODE), 'created_at' => now(),
        ]);
    }

    /** The top-level keys that changed (names only: enough for an audit trail). */
    private static function diff(array $before, array $after): array
    {
        return array_values(array_filter(array_unique(array_merge(array_keys($before), array_keys($after))), fn ($k) => ($before[$k] ?? null) != ($after[$k] ?? null)));
    }
}
