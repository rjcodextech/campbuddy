<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentLog;
use App\Models\Event;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Support\DealForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Captures the contact form an attendee fills in before opening a deal
 * that has Offer::capture_leads enabled (§7) — the fields that deal's own
 * form asks for (DealForm). A one-shot public write, same
 * lightweight-validation posture as BookmarkController — no owner-token
 * lifecycle needed since there's nothing here for the submitter to later
 * update or delete.
 */
class OfferLeadController extends Controller
{
    public function store(Request $request, Event $event, Offer $offer): JsonResponse
    {
        // A switched-off deal isn't shown to attendees, so it takes no leads
        // either; nor does a default deal this event doesn't show.
        abort_unless($offer->isShownAt($event), 404);
        abort_unless($offer->capture_leads, 422, 'This deal does not require contact info.');

        $data = $request->validate(DealForm::rules($offer->leadForm()));
        $clean = fn (?string $value) => filled($value) ? trim($value) : null;
        $email = mb_strtolower(trim($data['email']));

        // Opening the same deal twice (or a double-tap) is one lead, not two:
        // the sponsor gets a clean list, and replaying the request can't pad it.
        OfferLead::updateOrCreate(
            ['offer_id' => $offer->id, 'event_id' => $event->id, 'email' => $email],
            [
                'name' => $clean($data['name'] ?? null),
                'company' => $clean($data['company'] ?? null),
                'mobile' => $clean($data['mobile'] ?? null),
                'choices' => ($data['choices'] ?? []) === [] ? null : array_values($data['choices']),
                'consented_at' => now(),
                'consent_ip' => $request->ip(),
            ]
        );

        // Record lawful consent under DPDPA 2023 s.6
        ConsentLog::create([
            'event_id' => $event->id,
            'subject_type' => 'offer_lead',
            'subject_identifier' => hash('sha256', $email),
            'purpose' => "offer_lead:{$offer->id}",
            'notice_version' => '1.0',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'consented_at' => now(),
        ]);

        return response()->json(null, 201);
    }
}
