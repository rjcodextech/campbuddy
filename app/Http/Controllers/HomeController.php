<?php

namespace App\Http\Controllers;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Support\EventCountry;
use App\Support\EventData;
use App\Support\EventTime;
use App\Support\HeroSpotlights;
use App\Support\SafeUrl;
use App\Support\SocialKit;
use Illuminate\View\View;

/**
 * Root route: always shows the WordCamp picker — every upcoming or current
 * event (up to LIMIT), soonest first, then the ones that finished in the last
 * week as "Completed" — rather than silently skipping it whenever
 * there's only one visible event. Multi-event browsing (§2.2) lands here
 * as more events go live; this is the always-reachable entry point,
 * including as a "WordCamps" destination from inside an event
 * (see attendee.partials.topbar).
 *
 * The page lists them all; picker-filter.js then shows the first few with a
 * "Load more" button and a country filter, in the browser only — so the
 * HTML stays the same for every visitor (safe to cache at the edge).
 */
class HomeController extends Controller
{
    private const LIMIT = 100;

    public function __invoke(): View
    {
        $lastDay = 'COALESCE(ends_on, starts_on)';

        $events = Event::where('status', 'active')
            ->where('is_visible', true)
            ->where(fn ($q) => $q->whereRaw("{$lastDay} IS NULL")->orWhereRaw("{$lastDay} >= ?", [today()->subDays(EventTime::LISTED_DAYS + 3)->toDateString()]))
            ->orderByRaw('starts_on IS NULL')
            ->orderBy('starts_on')
            ->orderBy('id')
            ->take(self::LIMIT * 4)
            ->get()
            ->tap(fn ($found) => EventTime::primeSessionDays($found))
            ->filter(fn (Event $event) => ! EventTime::isOver($event) || EventTime::listed($event))
            ->partition(fn (Event $event) => ! EventTime::isOver($event))
            ->pipe(fn ($parts) => $parts[0]->concat($parts[1]->sortByDesc(fn (Event $event) => EventTime::lastDay($event))))
            ->take(self::LIMIT)
            ->values();

        $completed = $events->filter(fn (Event $event) => EventTime::isOver($event))->pluck('id')->flip()->all();

        $countries = $events->mapWithKeys(fn (Event $event) => [$event->id => EventCountry::code($event)]);
        $codes = $countries->filter()->unique()->sort()->values();
        $countryNames = $codes->mapWithKeys(fn ($code) => [$code => EventCountry::name($code)])->all();

        $eventStats = [];
        foreach ($events as $ev) {
            $sessions = EventData::get($ev->id, 'sessions') ?? [];
            $sponsors = EventData::get($ev->id, 'sponsors') ?? [];
            $code = $countries[$ev->id] ?? null;
            $eventStats[$ev->id] = [
                'sessions' => is_array($sessions) ? count($sessions) : 0,
                'sponsors' => is_array($sponsors) ? count($sponsors) : 0,
                'country_name' => $code ? ($countryNames[$code] ?? $code) : '',
            ];
        }

        // Prefer flagship upcoming event with rich synced schedule (e.g. Delhi / upcoming IN event, else soonest upcoming)
        $upcoming = $events->reject(fn (Event $ev) => isset($completed[$ev->id]));
        $featuredEvent = $upcoming
            ->sortByDesc(fn (Event $ev) => (($countries[$ev->id] ?? '') === 'IN' ? 1000 : 0) + min(500, $eventStats[$ev->id]['sessions'] ?? 0))
            ->first() ?? $events->first();

        $featuredMeta = null;
        if ($featuredEvent) {
            $fSessions = EventData::get($featuredEvent->id, 'sessions') ?? [];
            $fSpeakers = EventData::get($featuredEvent->id, 'speakers') ?? [];
            $fSponsors = EventData::get($featuredEvent->id, 'sponsors') ?? [];
            $fRosterCount = AttendeeRoster::where('event_id', $featuredEvent->id)->where('is_suppressed', false)->count();

            $avatars = [];
            foreach ($fSpeakers as $sp) {
                $photo = SafeUrl::web($sp['avatar_url'] ?? $sp['avatar'] ?? null);
                if (! empty($sp['name']) && $photo) {
                    $avatars[] = [
                        'name' => $sp['name'],
                        'photo' => $photo,
                    ];
                }
                if (count($avatars) >= 4) {
                    break;
                }
            }

            $featuredMeta = [
                'url' => route('event.home', $featuredEvent),
                'sessions' => is_array($fSessions) ? count($fSessions) : 0,
                'speakers' => is_array($fSpeakers) ? count($fSpeakers) : 0,
                'sponsors' => is_array($fSponsors) ? count($fSponsors) : 0,
                'roster' => $fRosterCount,
                'dates' => $featuredEvent->starts_on ? SocialKit::dates($featuredEvent) : null,
                'venue' => $featuredEvent->info['venue'] ?? null,
                'avatars' => $avatars,
            ];
        }

        return view('welcome', [
            'events' => $events,
            'completed' => $completed,
            'eventCountries' => $countries->all(),
            'countryNames' => $countryNames,
            'countryZones' => EventCountry::timezones($codes),
            'heroSpotlights' => HeroSpotlights::forEvents($events),
            'featuredEvent' => $featuredEvent,
            'featuredMeta' => $featuredMeta,
            'eventStats' => $eventStats,
        ]);
    }
}
