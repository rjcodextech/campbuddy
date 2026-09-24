<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DataRefresher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

/**
 * "Refresh event data" — fetch every live event's data now. The work is in
 * DataRefresher. It calls the WordCamp sites on every press, so a short
 * cooldown and a lock keep it from being hammered (it clears no cache, so
 * these can live there).
 */
class DataRefreshController extends Controller
{
    private const COOLDOWN_SECONDS = 30;

    public function __invoke(DataRefresher $refresher): RedirectResponse
    {
        if (Cache::has('admin:data-refresh:recent')) {
            return redirect()->route('dashboard')->withErrors([
                'refresh' => 'Event data was refreshed a moment ago — give it half a minute before refreshing again.',
            ]);
        }

        $lock = Cache::lock('admin:data-refresh', 180);

        if (! $lock->get()) {
            return redirect()->route('dashboard')->withErrors([
                'refresh' => 'A refresh is already running — it will finish in a moment.',
            ]);
        }

        try {
            $report = $refresher->refresh();
            Cache::put('admin:data-refresh:recent', true, self::COOLDOWN_SECONDS);
        } finally {
            $lock->release();
        }

        return redirect()->route('dashboard')->with('status', $report['message']);
    }
}
