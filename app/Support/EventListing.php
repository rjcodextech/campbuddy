<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * How the admin panel lists events: by event day, soonest first.
 *
 * Upcoming and ongoing events come first, the one that starts soonest on top;
 * events that are over follow, the most recent first; events with no date at
 * all go last (a discovered draft often has none). "Over" means the last day
 * (the end date, or the start date when there is no end date) is before today.
 *
 * Dates are compared as plain dates in the app's time zone — precise enough to
 * sort a list; the attendee side uses EventTime for anything that matters to
 * the minute.
 */
class EventListing
{
    public const STATUSES = ['draft', 'approved', 'active', 'archived'];

    public const WHEN = ['upcoming' => 'Upcoming', 'past' => 'Past', 'undated' => 'No date yet'];

    /** How many of the nearest upcoming events the list highlights. */
    public const HIGHLIGHT = 10;

    /** The event's last day as SQL: the end date, or the start date when there's no end date. */
    private const LAST_DAY = 'COALESCE(ends_on, starts_on)';

    public static function today(): string
    {
        return now()->toDateString();
    }

    /**
     * The filters the request asked for, cleaned: anything unknown is dropped
     * rather than trusted, so a hand-edited URL can't produce an odd query.
     *
     * @return array{q: string, status: ?string, visibility: ?string, when: ?string}
     */
    public static function filters(Request $request): array
    {
        $pick = fn (string $key, array $allowed): ?string => in_array($request->query($key), $allowed, true) ? $request->query($key) : null;

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'status' => $pick('status', self::STATUSES),
            'visibility' => $pick('visibility', ['visible', 'hidden']),
            'when' => $pick('when', array_keys(self::WHEN)),
        ];
    }

    /**
     * @param  Builder<Event>  $query
     * @param  array{q: string, status: ?string, visibility: ?string, when: ?string}  $filters
     * @return Builder<Event>
     */
    public static function filtered(Builder $query, array $filters, ?string $today = null): Builder
    {
        $today ??= self::today();
        $last = self::LAST_DAY;

        return $query
            ->when($filters['q'] !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('display_name', 'like', '%'.$filters['q'].'%')
                ->orWhere('slug', 'like', '%'.$filters['q'].'%')
                ->orWhere('short_name', 'like', '%'.$filters['q'].'%')))
            ->when($filters['status'], fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['visibility'], fn (Builder $q, string $v) => $q->where('is_visible', $v === 'visible'))
            ->when($filters['when'] === 'upcoming', fn (Builder $q) => $q->whereNotNull('starts_on')->whereRaw("{$last} >= ?", [$today]))
            ->when($filters['when'] === 'past', fn (Builder $q) => $q->whereNotNull('starts_on')->whereRaw("{$last} < ?", [$today]))
            ->when($filters['when'] === 'undated', fn (Builder $q) => $q->whereNull('starts_on'));
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public static function ordered(Builder $query, ?string $today = null): Builder
    {
        $today ??= self::today();
        $last = self::LAST_DAY;

        return $query
            // 0 = upcoming/ongoing, 1 = over, 2 = no date.
            ->orderByRaw("CASE WHEN starts_on IS NULL THEN 2 WHEN {$last} >= ? THEN 0 ELSE 1 END", [$today])
            // Within the first group: soonest start first (the other groups sort as one value here).
            ->orderByRaw("CASE WHEN starts_on IS NOT NULL AND {$last} >= ? THEN starts_on END ASC", [$today])
            // Within the "over" group: most recent first.
            ->orderByDesc('starts_on')
            ->orderBy('display_name')
            ->orderBy('id');
    }

    /**
     * The nearest upcoming or ongoing events, in order — the ones the list
     * highlights. Not narrowed by the filters, so the same ten stand out
     * whatever the admin is looking at.
     *
     * @return Collection<int, Event>
     */
    public static function highlighted(?string $today = null): Collection
    {
        $today ??= self::today();

        return Event::query()
            ->select(['id', 'starts_on', 'ends_on'])
            ->whereNotNull('starts_on')
            ->whereRaw(self::LAST_DAY.' >= ?', [$today])
            ->orderBy('starts_on')
            ->orderBy('display_name')
            ->orderBy('id')
            ->limit(self::HIGHLIGHT)
            ->get();
    }

    /**
     * Where an event is in time, in words: "Happening now", "Tomorrow",
     * "In 5 days", "Ended 3 days ago". Null when it has no date.
     *
     * @return array{state: string, label: string}|null  state: ongoing | upcoming | past
     */
    public static function timing(Event $event, ?string $today = null): ?array
    {
        if ($event->starts_on === null) {
            return null;
        }

        $today = CarbonImmutable::parse($today ?? self::today())->startOfDay();
        $start = CarbonImmutable::parse($event->starts_on->toDateString())->startOfDay();
        $end = CarbonImmutable::parse(($event->ends_on ?? $event->starts_on)->toDateString())->startOfDay();

        if ($end->lt($today)) {
            $days = (int) round($end->diffInDays($today));

            return ['state' => 'past', 'label' => $days === 1 ? 'Ended yesterday' : "Ended {$days} days ago"];
        }

        if ($start->lte($today)) {
            return ['state' => 'ongoing', 'label' => 'Happening now'];
        }

        $days = (int) round($today->diffInDays($start));

        return ['state' => 'upcoming', 'label' => $days === 1 ? 'Tomorrow' : "In {$days} days"];
    }
}
