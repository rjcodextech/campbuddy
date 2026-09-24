<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CachePurger;
use App\Support\CacheVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * "Purge cache & refresh data" — one button that makes the website and every
 * installed PWA show fresh data. All the work is in CachePurger.
 *
 * It fetches from the WordCamp sites (and Cloudflare's API) on every press, so
 * it must not be hammered — but not with the `throttle` middleware: that keeps
 * its counters in the application cache, which is exactly what a purge empties,
 * so each purge would reset its own throttle. The cooldown below reads the
 * timestamp of the last purge from CacheVersion's file, which survives it.
 */
class CachePurgeController extends Controller
{
    private const COOLDOWN_SECONDS = 10;

    public function __invoke(Request $request, CachePurger $purger): RedirectResponse
    {
        $last = CacheVersion::last();

        if ($last && Carbon::parse($last['purged_at'])->gt(now()->subSeconds(self::COOLDOWN_SECONDS))) {
            return redirect()->route('dashboard')->withErrors([
                'purge' => 'The cache was purged a few seconds ago — give it a moment before purging again.',
            ]);
        }

        // A second click (or a second admin) while a purge is still running.
        $lock = Cache::lock('admin:cache-purge', 180);

        if (! $lock->get()) {
            return redirect()->route('dashboard')->withErrors([
                'purge' => 'A purge is already running — it will finish in a moment.',
            ]);
        }

        try {
            $user = $request->user();
            $report = $purger->purge($user?->email ?? $user?->name);
        } finally {
            $lock->release();
        }

        return redirect()->route('dashboard')->with('status', $report['message']);
    }
}
