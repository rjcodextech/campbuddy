<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Offer;
use App\Models\OfferLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Captures the Name/Email/Mobile an attendee submits before opening a
 * deal that has Offer::capture_leads enabled (§7). A one-shot public
 * write, same lightweight-validation posture as BookmarkController — no
 * owner-token lifecycle needed since there's nothing here for the
 * submitter to later update or delete.
 */
class OfferLeadController extends Controller
{
    public function store(Request $request, Event $event, Offer $offer): JsonResponse
    {
        abort_unless($offer->event_id === $event->id, 404);
        abort_unless($offer->capture_leads, 422, 'This deal does not require contact info.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'mobile' => ['nullable', 'string', 'max:32'],
        ]);

        OfferLead::create([
            ...$data,
            'event_id' => $event->id,
            'offer_id' => $offer->id,
        ]);

        return response()->json(null, 201);
    }
}
