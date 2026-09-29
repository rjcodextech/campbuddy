<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FreeStealSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Suggest a Free Steal" (Explore → Free Steals). A one-shot public write
 * like OfferLeadController: nothing is published from it, an admin reviews
 * it (Admin → Free Steals). A bot that fills the hidden `website` field, a
 * repeat of the same link from the same event, and anything past
 * FreeStealSuggestion::MAX_WAITING are all answered 201 and not stored.
 */
class FreeStealSuggestionController extends Controller
{
    public function store(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'maker' => ['nullable', 'string', 'max:120'],
            'why' => ['nullable', 'string', 'max:300'],
            'email' => ['nullable', 'email', 'max:191'],
            'website' => ['nullable', 'string', 'max:500'],
        ]);

        if (filled($data['website'] ?? null) || FreeStealSuggestion::count() >= FreeStealSuggestion::MAX_WAITING) {
            return response()->json(null, 201);
        }

        $clean = fn (?string $value) => filled($value) ? trim($value) : null;

        FreeStealSuggestion::firstOrCreate(
            ['event_id' => $event->id, 'url' => trim($data['url'])],
            [
                'name' => trim($data['name']),
                'maker' => $clean($data['maker'] ?? null),
                'why' => $clean($data['why'] ?? null),
                'email' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
            ]
        );

        return response()->json(null, 201);
    }
}
