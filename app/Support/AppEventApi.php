<?php

namespace App\Support;

use App\Models\ContributorTable;
use App\Models\Event;
use App\Models\FreeSteal;
use App\Models\Offer;
use App\Models\Quest;
use Illuminate\Support\Facades\Cache;

/**
 * The JSON the native app reads about a WordCamp (GET /api/v1/app/events…).
 * The same data the website pages are built from, in a stable shape: sessions
 * with "running late" applied, speakers with plain-text bios, sponsors, deals,
 * free steals, quests, Contributor Day tables, the first-timer guide, memories.
 * Shapes are pinned by tests/Feature/AppEventApiTest.php and the files in
 * schema/api/.
 */
class AppEventApi
{
    /** Events the picker lists, in the picker's order (the same rule as HomeController). */
    public static function listed(?string $country = null, bool $includePast = true)
    {
        $lastDay = 'COALESCE(ends_on, starts_on)';

        $events = Event::where('status', 'active')
            ->where('is_visible', true)
            ->where(fn ($q) => $q->whereRaw("{$lastDay} IS NULL")->orWhereRaw("{$lastDay} >= ?", [today()->subDays(EventTime::LISTED_DAYS + 3)->toDateString()]))
            ->orderByRaw('starts_on IS NULL')
            ->orderBy('starts_on')
            ->orderBy('id')
            ->take(400)
            ->get()
            ->tap(fn ($found) => EventTime::primeSessionDays($found))
            ->filter(fn (Event $event) => ! EventTime::isOver($event) || EventTime::listed($event))
            ->partition(fn (Event $event) => ! EventTime::isOver($event))
            ->pipe(fn ($parts) => $parts[0]->concat($parts[1]->sortByDesc(fn (Event $event) => EventTime::lastDay($event))))
            ->take(100)
            ->values();

        if ($country) {
            $events = $events->filter(fn (Event $e) => EventCountry::code($e) === strtoupper($country))->values();
        }
        if (! $includePast) {
            $events = $events->reject(fn (Event $e) => EventTime::isOver($e))->values();
        }

        return $events;
    }

    /** @return array<string, mixed> */
    public static function card(Event $event): array
    {
        return [
            'slug' => $event->slug,
            'name' => $event->display_name,
            'short_name' => $event->short_name,
            'logo' => $event->logoUrl() ?? $event->faviconUrl(),
            'mark' => $event->markUrl(),
            'starts_on' => $event->starts_on?->toDateString(),
            'ends_on' => $event->ends_on?->toDateString(),
            'first_day' => EventTime::firstDay($event),
            'last_day' => EventTime::lastDay($event),
            'timezone' => EventTime::zone($event)->getName(),
            'country' => EventCountry::code($event),
            'completed' => EventTime::isOver($event),
            'website' => $event->source_site_url,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Event $event): array
    {
        $hero = SiteTheme::hero($event);

        return self::card($event) + [
            'tagline' => SiteTheme::tagline($event),
            'hero_art' => $hero ? ['url' => url($hero['url']), 'kind' => $hero['kind'] ?? null] : null,
            'info' => is_array($event->info) ? $event->info : [],
            'memories' => EventMemories::pageIsPublic($event) ? EventMemories::links($event) : (object) [],
            'delay' => self::delay($event),
            'data_version' => DataVersion::for($event),
            'retention_ends_at' => EventTime::retentionEnd($event)?->toIso8601String(),
        ];
    }

    /** The "running late" notices that apply now. */
    public static function delay(Event $event): array
    {
        return array_map(fn (array $d) => $d + ['text' => ScheduleDelay::describe($event, $d)], ScheduleDelay::active($event));
    }

    /** @return list<array<string, mixed>> */
    public static function sessions(Event $event): array
    {
        $sessions = ScheduleDelay::apply($event, EventData::get($event->id, 'sessions') ?? []);

        return array_values(array_map(function ($s) {
            $start = ! empty($s['starts_at']) ? strtotime((string) $s['starts_at']) : null;
            $duration = $s['duration_seconds'] ?? null;

            return $s + ['ends_at' => $start && $duration ? gmdate('c', $start + $duration) : null];
        }, array_filter($sessions, 'is_array')));
    }

    /** @return list<array<string, mixed>> speakers with a plain-text bio (the stored markup is about 7 KB each). */
    public static function speakers(Event $event): array
    {
        $speakers = EventData::get($event->id, 'speakers') ?? [];
        $key = 'app-speakers:'.md5((string) json_encode($speakers));

        return Cache::remember($key, now()->addDay(), fn () => array_values(array_map(function ($speaker) {
            $speaker = is_array($speaker) ? $speaker : [];
            $bio = HtmlText::plain($speaker['bio_html'] ?? null, 1200);
            unset($speaker['bio_html']);

            return $speaker + ['bio_text' => $bio];
        }, $speakers)));
    }

    /** @return list<array<string, mixed>> */
    public static function sponsors(Event $event): array
    {
        return array_values(array_map(function ($sponsor) {
            $sponsor = is_array($sponsor) ? $sponsor : [];
            $text = HtmlText::plain($sponsor['description_html'] ?? null, 600);
            unset($sponsor['description_html']);

            return $sponsor + ['description' => $text];
        }, EventData::get($event->id, 'sponsors') ?? []));
    }

    /** @return list<array<string, mixed>> */
    public static function organizers(Event $event): array
    {
        return array_values(EventData::get($event->id, 'organizers') ?? []);
    }

    /** @return list<array<string, mixed>> */
    public static function deals(Event $event): array
    {
        return Offer::shownAt($event)->load('mediaAsset')->map(fn (Offer $offer) => [
            'id' => $offer->id,
            'brand' => $offer->displayName(),
            'title' => $offer->title,
            'highlight' => $offer->highlight,
            'description' => $offer->description,
            'terms' => $offer->terms,
            'coupon_code' => $offer->coupon_code,
            'url' => SafeUrl::web($offer->url),
            'website' => $offer->displayWebsite(),
            'cta_label' => $offer->cta_label,
            'logo' => $offer->mediaAsset?->url(),
            'icon' => $offer->icon,
            'is_default' => $offer->isDefault(),
            'capture_leads' => (bool) $offer->capture_leads,
            'lead_form' => $offer->capture_leads ? $offer->leadForm() : null,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public static function freeSteals(Event $event): array
    {
        return FreeSteal::forEvent($event)->map(fn (FreeSteal $steal) => [
            'id' => $steal->id,
            'name' => $steal->name,
            'description' => $steal->description,
            'maker' => $steal->maker,
            'category' => $steal->category,
            'url' => SafeUrl::web($steal->url),
            'cta_label' => $steal->cta_label,
            'logo' => $steal->mediaAsset?->url(),
            'featured' => (bool) $steal->is_featured,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public static function quests(Event $event): array
    {
        return Quest::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('event_id')->orWhere('event_id', $event->id))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Quest $q) => ['id' => $q->id, 'title' => $q->title, 'description' => $q->description, 'source' => $q->source])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public static function tables(Event $event): array
    {
        return collect(ContributorTable::forEvent($event))->map->publicData()->values()->all();
    }

    /** @return array<string, mixed> the first-timer guide, as data */
    public static function guide(): array
    {
        return [
            'day' => FirstTimerGuide::day(),
            'moments' => FirstTimerGuide::moments(),
            'glossary' => FirstTimerGuide::glossary(),
            'tips' => FirstTimerGuide::tips(),
            'students' => ['intro' => FirstTimerGuide::studentIntro(), 'points' => FirstTimerGuide::students()],
            'bring' => FirstTimerGuide::bring(),
            'faq' => FirstTimerGuide::faq(),
            'quick_questions' => FirstTimerGuide::quickQuestions(),
        ];
    }
}
