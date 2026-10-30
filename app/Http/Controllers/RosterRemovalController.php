<?php

namespace App\Http\Controllers;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Support\PersonName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The takedown path: a visible, no-login-required way for anyone to
 * request removal from the ingested roster — self-service immediate
 * suppression by name match, no email infrastructure required. This is
 * a pre-launch blocker, not deferrable once real people's data is
 * involved.
 */
class RosterRemovalController extends Controller
{
    public function show(Event $event): View
    {
        return view('attendee.roster-removal', ['event' => $event, 'matches' => null]);
    }

    public function search(Event $event, Request $request): View
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:191']])['name'];

        // Compared without invisible characters and doubled spaces on either side (PersonName), so
        // "Arif" finds an entry the site stored as "Arif\u{200B}" — nobody can type what they can't see.
        $wanted = PersonName::key($name);
        $matches = $wanted === '' ? collect() : $event->attendeeRoster()
            ->where('is_suppressed', false)
            ->get()
            ->filter(fn ($entry) => PersonName::key((string) $entry->name) === $wanted)
            ->values();

        return view('attendee.roster-removal', ['event' => $event, 'matches' => $matches, 'searchedName' => $name]);
    }

    public function remove(Event $event, AttendeeRoster $entry, Request $request): RedirectResponse
    {
        abort_unless($entry->event_id === $event->id, 404);

        // No exceptions, not even for tests: the token comes with the search result (removalToken()).
        if (! hash_equals(self::removalToken($entry), (string) $request->input('removal_token'))) {
            abort(403, 'Invalid or expired removal token.');
        }

        $entry->suppress();

        return redirect()
            ->route('event.roster-removal.show', $event)
            ->with('status', "{$entry->name} has been removed from the public roster.");
    }

    /** The proof that a removal came from a search result for that entry (shown in the result's form). */
    public static function removalToken(AttendeeRoster $entry): string
    {
        return hash_hmac('sha256', $entry->id.':'.$entry->content_hash, (string) config('app.key'));
    }
}
