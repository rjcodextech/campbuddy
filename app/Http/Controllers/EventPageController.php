<?php

namespace App\Http\Controllers;

use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Models\Quest;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

/**
 * Server-rendered attendee pages — one controller per tab.
 * Branding and schedule data are read here and injected into the Blade
 * shell directly; nothing about the *content* of a page requires a
 * client-side fetch before first paint.
 */
class EventPageController extends Controller
{
    public function home(Event $event): View
    {
        $sessions = $this->cached($event, 'sessions');
        $quests = $this->questsFor($event);

        return view('attendee.home', [
            'event' => $event,
            'sessions' => $sessions,
            'quests' => $quests,
            'now' => now()->toIso8601String(),
        ]);
    }

    public function myDay(Event $event): View
    {
        return view('attendee.my-day', [
            'event' => $event,
            'sessions' => $this->cached($event, 'sessions'),
            'speakers' => $this->cached($event, 'speakers'),
        ]);
    }

    public function quest(Event $event): View
    {
        return view('attendee.quest', [
            'event' => $event,
            'quests' => $this->questsFor($event),
        ]);
    }

    public function contribute(Event $event): View
    {
        // CD4's quest tie-in needs this quest's real ID so the JS can
        // mark it complete directly — not a title-matching guess.
        $contributorDayQuestId = Quest::where('source', 'contributor_day')
            ->where(function ($q) use ($event) {
                $q->whereNull('event_id')->orWhere('event_id', $event->id);
            })
            ->where('is_active', true)
            ->value('id');

        return view('attendee.contribute', [
            'event' => $event,
            'contributorDayQuestId' => $contributorDayQuestId,
        ]);
    }

    public function explore(Event $event): View
    {
        return view('attendee.explore', [
            'event' => $event,
            'sponsors' => $this->cached($event, 'sponsors'),
            'offers' => $event->offers()->with('mediaAsset')->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function campCard(Event $event): View
    {
        return view('attendee.camp-card', ['event' => $event]);
    }

    /**
     * One of the event's ingested lists (sessions, speakers, sponsors) from
     * the cache. A miss means the data has never been fetched or has expired,
     * so the page renders empty this once — and a refresh is queued right
     * away, rather than leaving the event blank until the next scheduled run
     * (which, if scheduling is misconfigured, never comes).
     *
     * @return array<int, mixed>
     */
    private function cached(Event $event, string $key): array
    {
        $value = Cache::get("event:{$event->id}:{$key}");

        if (! is_array($value)) {
            $this->requestIngest($event);

            return [];
        }

        return $value;
    }

    /**
     * At most one refresh per event per five minutes: a room full of
     * attendees opening the app on a cold cache must not each trigger a fetch
     * against the WordCamp site.
     */
    private function requestIngest(Event $event): void
    {
        if (! Cache::add("event:{$event->id}:ingest-requested", true, now()->addMinutes(5))) {
            return;
        }

        try {
            FetchSpeakersSponsorsSessionsJob::dispatch($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Quest>
     */
    private function questsFor(Event $event)
    {
        return Quest::where('is_active', true)
            ->where(function ($q) use ($event) {
                $q->whereNull('event_id')->orWhere('event_id', $event->id);
            })
            ->orderBy('sort_order')
            ->get();
    }
}
