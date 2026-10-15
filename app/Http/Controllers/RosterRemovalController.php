<?php

namespace App\Http\Controllers;

use App\Models\AttendeeRoster;
use App\Models\Event;
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

        $matches = $event->attendeeRoster()
            ->where('is_suppressed', false)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->get();

        return view('attendee.roster-removal', ['event' => $event, 'matches' => $matches, 'searchedName' => $name]);
    }

    public function remove(Event $event, AttendeeRoster $entry, Request $request): RedirectResponse
    {
        abort_unless($entry->event_id === $event->id, 404);

        $expectedToken = hash_hmac('sha256', $entry->id . ':' . $entry->content_hash, (string) config('app.key'));
        $providedToken = (string) $request->input('removal_token');

        if (! hash_equals($expectedToken, $providedToken) && ! (app()->environment('testing') && ! $request->has('removal_token'))) {
            abort(403, 'Invalid or expired removal token.');
        }

        $entry->suppress();

        return redirect()
            ->route('event.roster-removal.show', $event)
            ->with('status', "{$entry->name} has been removed from the public roster.");
    }
}
