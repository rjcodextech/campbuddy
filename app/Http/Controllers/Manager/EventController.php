<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Http\Requests\Manager\UpdateManagedEventInfoRequest;
use App\Http\Requests\Manager\UpdateManagedEventRequest;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Support\EventEdits;
use App\Support\ManagerActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

/**
 * An event manager's two event pages: "Event details" and "Event
 * information". Every method starts from the manager's own events, so an
 * event that isn't theirs is a 404 whatever id is asked for — and the lifecycle
 * status is not part of any form here, so it can't be changed.
 *
 * Every change is written to the activity log (ManagerActivity) for the admin.
 */
class EventController extends Controller
{
    use ResolvesManagedEvent;

    public function details(Request $request, string $eventId): View
    {
        return view('manager.events.details', ['event' => $this->managedEvent($request, $eventId)]);
    }

    public function updateDetails(UpdateManagedEventRequest $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $zoneBefore = $event->timezone;
        $before = ManagerActivity::detailsSnapshot($event);

        $event->update(EventEdits::details($request->validated(), $event));

        if ($change = ManagerActivity::detailsChange($before, ManagerActivity::detailsSnapshot($event))) {
            ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'details', 'updated', $change);
        }

        // Session times depend on the zone: re-read them in the new one (as the admin's save does).
        // At most once in five minutes per event: each re-read is a round of requests to the WordCamp
        // site, and the 15-minute schedule picks the new zone up anyway.
        if ($event->timezone !== $zoneBefore && $event->status === 'active' && Cache::add("manager-refetch:{$event->id}", 1, now()->addMinutes(5))) {
            try {
                FetchSpeakersSponsorsSessionsJob::dispatch($event);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('manager.events.details', $event)->with('status', 'Event details saved.');
    }

    public function information(Request $request, string $eventId): View
    {
        return view('manager.events.information', ['event' => $this->managedEvent($request, $eventId)]);
    }

    public function updateInformation(UpdateManagedEventInfoRequest $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $before = $event->info ?? [];

        $event->update(['info' => EventEdits::info($request->validated())]);

        if ($change = ManagerActivity::infoChange($before, $event->info ?? [])) {
            ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'information', 'updated', $change);
        }

        return redirect()->route('manager.events.information', $event)->with('status', 'Event information saved.');
    }
}
