<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Quest;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Server-rendered attendee pages (§5.1) — one controller per §1.2 tab.
 * Branding and schedule data are read here and injected into the Blade
 * shell directly; nothing about the *content* of a page requires a
 * client-side fetch before first paint (§3.1 H6).
 */
class EventPageController extends Controller
{
    public function home(Event $event): View
    {
        $sessions = Cache::get("event:{$event->id}:sessions", []);
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
            'sessions' => Cache::get("event:{$event->id}:sessions", []),
            'speakers' => Cache::get("event:{$event->id}:speakers", []),
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
            'sponsors' => Cache::get("event:{$event->id}:sponsors", []),
            'offers' => $event->offers()->with('mediaAsset')->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function campCard(Event $event): View
    {
        return view('attendee.camp-card', ['event' => $event]);
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
