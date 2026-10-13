<?php

namespace App\Support;

use App\Http\Controllers\Api\DiscoveryController;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;

/**
 * What the Reports tab (admin and event manager) shows and does: the open
 * reports grouped by profile, taking a profile down, dismissing reports.
 */
class DiscoveryReports
{
    /** @return array{open: \Illuminate\Support\Collection, closed: \Illuminate\Support\Collection, stillThere: list<string>} */
    public static function forEvent(Event $event): array
    {
        $open = DiscoveryReport::where('event_id', $event->id)->where('status', 'open')->latest()->get()->groupBy('discovery_id');

        return [
            'open' => $open,
            'closed' => DiscoveryReport::where('event_id', $event->id)->where('status', '!=', 'open')->latest('resolved_at')->limit(30)->get(),
            'stillThere' => DiscoveryProfile::where('event_id', $event->id)->whereIn('discovery_id', $open->keys())->pluck('discovery_id')->all(),
        ];
    }

    /** Takes the profile down (gone from every phone within seconds) and closes its reports. */
    public static function remove(Event $event, string $discoveryId): void
    {
        DiscoveryProfile::where('event_id', $event->id)->where('discovery_id', $discoveryId)->delete();
        Cache::forget(DiscoveryController::listKey($event));
        self::close($event, $discoveryId, 'removed');
    }

    public static function dismiss(Event $event, string $discoveryId): void
    {
        self::close($event, $discoveryId, 'dismissed');
    }

    private static function close(Event $event, string $discoveryId, string $status): void
    {
        DiscoveryReport::where('event_id', $event->id)->where('discovery_id', $discoveryId)->where('status', 'open')
            ->update(['status' => $status, 'resolved_at' => now()]);
    }
}
