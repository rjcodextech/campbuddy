<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscoveryProfile;
use App\Models\DiscoveryReport;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        DiscoveryReport::updateOrCreate(
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

        return response()->json(null, 201);
    }
}
