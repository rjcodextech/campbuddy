<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use App\Support\NativePush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * POST /api/v1/events/{event}/discovery/{discoveryId}/report — "Report" on a
 * match. Anonymous like the rest of discovery (the reporting phone is
 * identified by its device id only); a phone can report a profile once, and
 * reporting again just updates its reason. Reviewed in Admin → Event → Reports.
 */
class DiscoveryReportController extends Controller
{
    public function store(Request $request, Event $event, string $discoveryId): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'in:'.implode(',', array_keys(DiscoveryReport::REASONS))],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $profile = DiscoveryProfile::where('event_id', $event->id)->where('discovery_id', $discoveryId)->with('rosterEntry:id,name')->first();
        abort_if($profile === null, 404);

        $card = $profile->publicCard();

        $report = DiscoveryReport::updateOrCreate(
            ['discovery_id' => $discoveryId, 'reporter_device_id' => $data['device_id']],
            [
                'event_id' => $event->id,
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
                'snapshot' => [
                    'name' => $card['name'],
                    'profession' => $card['fields']['profession'],
                    'who_to_meet' => $card['fields']['who_to_meet'],
                    'tags' => $card['fields']['tags'],
                ],
                'status' => 'open',
                'resolved_at' => null,
            ]
        );

        // A phone alert for the organizers, at most one per profile an hour (a pile-on is one alert).
        if ($report->wasRecentlyCreated && Cache::add("report-alert:{$event->id}:{$discoveryId}", 1, 3600)) {
            NativePush::toOrganizers($event, [
                'title' => 'A profile was reported',
                'body' => "{$event->display_name}: review it in Reports.",
            ], 'report-'.$event->id);
        }

        return response()->json(null, 201);
    }
}
