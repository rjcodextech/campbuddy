<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\SafeUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/events/{slug}/roster — the ingested Attendees-page
 * mirror. Paginated (the attendee app fetches every page and renders
 * the full roster in one flowing list — see people.js — pagination
 * here just keeps any single response bounded) and never includes
 * suppressed entries.
 */
class RosterController extends Controller
{
    public function __invoke(Event $event, Request $request): JsonResponse
    {
        try {
            $roster = $this->page($event, withOpenToMeet: true);
        } catch (QueryException $e) {
            // The discovery link column isn't there yet (a deploy that hasn't
            // run `php artisan migrate`). The attendee list must still load —
            // just without the "open to meet" marks. The admin dashboard flags
            // the pending migration.
            report($e);
            $roster = $this->page($event, withOpenToMeet: false);
        }

        return response()->json($roster);
    }

    private function page(Event $event, bool $withOpenToMeet): LengthAwarePaginator
    {
        return $event->attendeeRoster()
            ->where('is_suppressed', false)
            // "Open to meet": they picked this entry as themselves in attendee
            // discovery — their own choice to be found. One subquery, not one per row.
            ->when($withOpenToMeet, fn ($q) => $q->withExists(['discoveryProfile as open_to_meet' => fn ($q) => $q->alive($event)]))
            ->orderBy('name')
            ->paginate(200)
            ->through(fn ($entry) => [
                // The id is what "that's me" in the discovery form sends back.
                'id' => $entry->id,
                'name' => $entry->name,
                'open_to_meet' => (bool) $entry->open_to_meet,
                // Only web addresses leave here, whatever was stored (rows
                // scraped before the scraper checked, or a future source).
                'gravatar_url' => SafeUrl::web($entry->gravatar_url),
                'links' => collect($entry->links ?? [])
                    ->filter(fn ($link) => SafeUrl::web($link['url'] ?? null) !== null)
                    ->values()
                    ->all(),
            ]);

    }
}
