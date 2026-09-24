<?php

namespace App\Http\Controllers;

use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Models\Event;
use App\Models\Quest;
use App\Support\HtmlText;
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
    /** Whole lines of a speaker page that are just a social-link button's label. */
    private const SOCIAL_BUTTON_LABELS = ['wordpress', 'linkedin', 'x', 'twitter', 'link', 'website', 'github', 'instagram', 'facebook', 'youtube'];

    public function home(Event $event): View
    {
        $sessions = $this->cached($event, 'sessions');
        $quests = $this->questsFor($event);

        return view('attendee.home', [
            'event' => $event,
            'sessions' => $sessions,
            'quests' => $quests,
        ]);
    }

    public function myDay(Event $event): View
    {
        return view('attendee.my-day', [
            'event' => $event,
            'sessions' => $this->cached($event, 'sessions'),
            'speakers' => $this->withPlainBios($this->cached($event, 'speakers')),
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
        // mark it complete directly — not a title-matching guess in the
        // browser. It's the seeded "Contribution Curious" default (or an
        // admin-made quest with the old dedicated source).
        $contributorDayQuestId = Quest::where('is_active', true)
            ->where(function ($q) use ($event) {
                $q->whereNull('event_id')->orWhere('event_id', $event->id);
            })
            ->where(function ($q) {
                $q->where('source', 'contributor_day')
                    ->orWhere(fn ($q) => $q->where('source', 'default')->where('title', Quest::CONTRIBUTION_CURIOUS));
            })
            ->orderBy('sort_order')
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

    /**
     * WordCamp 101 for this event: the general first-timer guide, plus this
     * event's own practical details and — client-side — the real times of
     * the moments the guide describes (registration, lunch, keynote…).
     */
    public function guide(Event $event): View
    {
        return view('attendee.guide', [
            'event' => $event,
            'sessions' => $this->cached($event, 'sessions'),
        ]);
    }

    public function campCard(Event $event): View
    {
        return view('attendee.camp-card', ['event' => $event]);
    }

    /**
     * A speaker's bio is stored as the raw WordPress block markup of their
     * speaker page — about 7 KB each, three quarters of the whole My Day page
     * — and the app only ever shows it as plain text. Send the text.
     *
     * @param  array<int, mixed>  $speakers
     * @return array<int, mixed>
     */
    private function withPlainBios(array $speakers): array
    {
        if ($speakers === []) {
            return [];
        }

        // Parsing every bio is the heaviest thing a page view does, and the
        // result only changes when the ingested speakers do — so it's keyed
        // on their content and worked out once, not on every My Day view.
        $key = 'speakers-plain:'.md5((string) json_encode($speakers));

        return Cache::remember($key, now()->addDay(), fn () => $this->toPlainBios($speakers));
    }

    /**
     * @param  array<int, mixed>  $speakers
     * @return array<int, mixed>
     */
    private function toPlainBios(array $speakers): array
    {
        return array_map(function ($speaker) {
            if (! is_array($speaker)) {
                return $speaker;
            }

            $speaker['bio_text'] = $this->plainText($speaker['bio_html'] ?? null, $speaker['name'] ?? null);
            unset($speaker['bio_html']);

            return $speaker;
        }, $speakers);
    }

    /**
     * A speaker bio's plain text, minus the parts of the speaker page's own
     * layout that aren't bio (see below).
     */
    private function plainText(?string $html, ?string $speakerName = null): ?string
    {
        $lines = HtmlText::lines($html);

        // The speaker's page wraps the bio in its own layout: a heading that
        // repeats their name, and the labels of the social-link buttons
        // ("LinkedIn", "X", "Link") as lines of their own. Neither is bio.
        if ($speakerName !== null && isset($lines[0]) && mb_strtolower($lines[0]) === mb_strtolower(trim($speakerName))) {
            array_shift($lines);
        }

        $lines = array_values(array_filter(
            $lines,
            fn (string $line) => ! in_array(mb_strtolower($line), self::SOCIAL_BUTTON_LABELS, true)
        ));

        return $lines === [] ? null : implode("\n", $lines);
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
