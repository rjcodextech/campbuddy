<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LineIcons;
use App\Support\MobileAppConfig;
use App\Support\MobileAppDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Mobile app: the editor for the native app's server-driven settings
 * (App\Support\MobileAppConfig). Every change goes to a draft; Publish makes
 * it live for every phone, Rollback restores an older version. A change that
 * would leave the app with unreadable text, a broken tab or an unknown
 * section is refused with the reason, never saved.
 */
class MobileAppController extends Controller
{
    public const TABS = ['overview', 'navigation', 'home', 'text', 'look', 'banner', 'switches', 'history'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', \App\Models\Event::class);

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';
        $config = MobileAppConfig::working();

        return view('admin.mobile.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'config' => $config,
            'hasDraft' => MobileAppConfig::hasDraft(),
            'liveVersion' => MobileAppConfig::liveVersion(),
            'history' => MobileAppConfig::history(),
            'icons' => array_merge(['home', 'dashboard'], array_keys(LineIcons::ICONS)),
            'conditions' => MobileAppDefaults::VISIBLE_IF,
            'errors_list' => session('config_errors', []),
        ]);
    }

    /** Applies one section's form to the working config and saves it as the draft. */
    public function save(Request $request, string $section): RedirectResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);
        abort_unless(in_array($section, ['navigation', 'home', 'text', 'look', 'banner', 'switches'], true), 404);

        $config = MobileAppConfig::working();
        $config = match ($section) {
            'navigation' => $this->navigation($request, $config),
            'home' => $this->home($request, $config),
            'text' => $this->text($request, $config),
            'look' => $this->look($request, $config),
            'banner' => $this->banner($request, $config),
            'switches' => $this->switches($request, $config),
        };

        $errors = MobileAppConfig::validate($config);
        if ($errors !== []) {
            return redirect()->route('admin.mobile.index', ['tab' => $this->tabFor($section)])->withInput()->with('config_errors', $errors);
        }

        MobileAppConfig::saveDraft($config, $request->user(), $section);

        return redirect()->route('admin.mobile.index', ['tab' => $this->tabFor($section)])->with('status', 'Saved to the draft. It is not live until you publish.');
    }

    public function publish(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);

        try {
            $version = MobileAppConfig::publish($request->user(), $request->input('note'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.mobile.index')->with('config_errors', explode("\n", $e->getMessage()));
        }

        return redirect()->route('admin.mobile.index')->with('status', "Version {$version} is live. Phones pick it up within a minute.");
    }

    public function discard(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);
        MobileAppConfig::discardDraft($request->user());

        return redirect()->route('admin.mobile.index')->with('status', 'Draft discarded.');
    }

    public function rollback(Request $request, int $version): RedirectResponse
    {
        Gate::authorize('viewAny', \App\Models\Event::class);

        try {
            $new = MobileAppConfig::rollback($version, $request->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.mobile.index', ['tab' => 'history'])->with('config_errors', explode("\n", $e->getMessage()));
        }

        return redirect()->route('admin.mobile.index', ['tab' => 'history'])->with('status', "Version {$version} is live again, as version {$new}.");
    }

    // ---- sections ------------------------------------------------------------------------------

    private function tabFor(string $section): string
    {
        return $section === 'text' ? 'text' : $section;
    }

    private function navigation(Request $request, array $config): array
    {
        foreach (['tabs' => 'tabs', 'organizer' => 'organizer_tabs'] as $where => $field) {
            $rows = (array) $request->input($field, []);
            $list = $where === 'tabs' ? $config['tabs'] : $config['organizer']['tabs'];

            foreach ($list as $i => $tab) {
                $row = $rows[$tab['key']] ?? [];
                $list[$i]['enabled'] = isset($row['enabled']);
                $list[$i]['order'] = (int) ($row['order'] ?? $tab['order']);
                $list[$i]['icon'] = (string) ($row['icon'] ?? $tab['icon']);
            }
            usort($list, fn ($a, $b) => $a['order'] <=> $b['order']);

            if ($where === 'tabs') {
                $config['tabs'] = $list;
            } else {
                $config['organizer']['tabs'] = $list;
            }
        }

        return $config;
    }

    private function home(Request $request, array $config): array
    {
        $rows = (array) $request->input('sections', []);
        $list = $config['home']['sections'];

        foreach ($list as $i => $section) {
            $row = $rows[$i] ?? [];
            $list[$i]['enabled'] = isset($row['enabled']);
            $list[$i]['order'] = (int) ($row['order'] ?? $section['order']);
            $list[$i]['visible_if'] = ($row['visible_if'] ?? '') !== '' ? $row['visible_if'] : null;
        }
        usort($list, fn ($a, $b) => $a['order'] <=> $b['order']);
        $config['home']['sections'] = $list;

        return $config;
    }

    private function text(Request $request, array $config): array
    {
        foreach (MobileAppDefaults::LOCALES as $locale) {
            foreach ((array) $request->input("strings.{$locale}", []) as $key => $value) {
                $config['strings'][$locale][str_replace('__', '.', $key)] = trim((string) $value);
            }
        }

        return $config;
    }

    private function look(Request $request, array $config): array
    {
        foreach (['primary', 'accent', 'background', 'ink', 'muted'] as $token) {
            $config['theme'][$token] = strtoupper(trim((string) $request->input($token, $config['theme'][$token])));
        }
        $config['theme']['radius'] = (int) $request->input('radius', $config['theme']['radius']);
        $config['theme']['logo'] = trim((string) $request->input('logo', '')) ?: null;
        $config['theme']['splash_text'] = trim((string) $request->input('splash_text', ''));

        return $config;
    }

    private function banner(Request $request, array $config): array
    {
        if (! $request->boolean('banner_on')) {
            $config['banner'] = null;

            return $config;
        }

        $type = (string) $request->input('action_type', '');
        $action = null;
        if ($type !== '') {
            $action = ['type' => $type];
            if ($type === 'url') {
                $action['url'] = trim((string) $request->input('action_url'));
            } elseif ($type === 'event') {
                $action['slug'] = trim((string) $request->input('action_slug'));
            } elseif ($type === 'tab') {
                $action['tab'] = trim((string) $request->input('action_tab'));
            }
        }

        $config['banner'] = [
            'id' => 'b'.now()->format('mdHi'),
            'text' => array_filter(['en' => trim((string) $request->input('text_en')), 'hi' => trim((string) $request->input('text_hi'))]),
            'style' => (string) $request->input('style', 'info'),
            'action' => $action,
            'until' => $request->filled('until') ? \Illuminate\Support\Carbon::parse($request->input('until'))->utc()->toIso8601String() : null,
        ];

        return $config;
    }

    private function switches(Request $request, array $config): array
    {
        foreach (MobileAppDefaults::FLAGS as $flag) {
            $config['flags'][$flag] = $request->boolean("flags.{$flag}");
        }
        foreach (['android', 'ios'] as $platform) {
            $config['min_app'][$platform] = trim((string) $request->input("min_app.{$platform}", $config['min_app'][$platform]));
        }

        return $config;
    }
}
