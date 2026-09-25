<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;

/**
 * When discovery matches may message each other: only while the event is on.
 *
 * Each day of the event (its start date to its end date, as dates *in the
 * event's own time zone*) gets one window:
 *
 *     opens  = that day's first session start − 1 hour
 *     closes = that day's last session end    + 1 hour
 *
 * Outside those windows — overnight, before the event, after it — messaging
 * is closed, whatever anyone's limit. A day of the event with no timed
 * sessions (schedule not published yet, a Contributor Day without talks)
 * falls back to 09:00–18:00 local, i.e. 08:00–19:00 with the hour either
 * side. A session without a length counts as 30 minutes.
 *
 * All comparisons are between absolute moments (UTC), so the server's own
 * time zone and the visitor's phone never matter; only the event's does.
 */
class ChatWindow
{
    public const MARGIN_MINUTES = 60;

    private const FALLBACK_START = '09:00';

    private const FALLBACK_END = '18:00';

    private const DEFAULT_SESSION_MINUTES = 30;

    /** A misconfigured date range must not produce a year of windows. */
    private const MAX_DAYS = 10;

    private DateTimeZone $zone;

    /** @var array<int, array{day: string, opens: CarbonImmutable, closes: CarbonImmutable}>|null */
    private ?array $windows = null;

    /**
     * @param  array<int, array<string, mixed>>|null  $sessions  normalized sessions (null = read the stored ones)
     */
    public function __construct(private readonly Event $event, private readonly ?array $sessions = null)
    {
        $this->zone = EventTime::zone($event);
    }

    public static function for(Event $event): self
    {
        return new self($event);
    }

    /** @return array<int, array{day: string, opens: CarbonImmutable, closes: CarbonImmutable}> */
    public function windows(): array
    {
        if ($this->windows !== null) {
            return $this->windows;
        }

        // Each timed session as [local day, start, end], all as absolute moments.
        $byDay = [];
        foreach ($this->sessions ?? EventData::get($this->event->id, 'sessions') ?? [] as $session) {
            if (! is_array($session) || empty($session['starts_at'])) {
                continue;
            }

            try {
                $start = CarbonImmutable::parse($session['starts_at'])->utc();
            } catch (\Throwable) {
                continue;
            }

            $seconds = is_numeric($session['duration_seconds'] ?? null) && $session['duration_seconds'] > 0
                ? (int) $session['duration_seconds']
                : self::DEFAULT_SESSION_MINUTES * 60;

            $day = $start->setTimezone($this->zone)->toDateString();
            $byDay[$day][] = [$start, $start->addSeconds($seconds)];
        }

        $windows = [];
        foreach ($this->days(array_keys($byDay)) as $day) {
            if (isset($byDay[$day])) {
                $opens = min(array_column($byDay[$day], 0));
                $closes = max(array_column($byDay[$day], 1));
            } else {
                $opens = CarbonImmutable::parse("{$day} ".self::FALLBACK_START, $this->zone)->utc();
                $closes = CarbonImmutable::parse("{$day} ".self::FALLBACK_END, $this->zone)->utc();
            }

            $windows[] = [
                'day' => $day,
                'opens' => $opens->subMinutes(self::MARGIN_MINUTES),
                'closes' => $closes->addMinutes(self::MARGIN_MINUTES),
            ];
        }

        usort($windows, fn ($a, $b) => $a['opens'] <=> $b['opens']);

        return $this->windows = $windows;
    }

    /** The window open at $now (closing moment excluded), if any. */
    public function current(?CarbonInterface $now = null): ?array
    {
        $now = CarbonImmutable::instance($now ?? now())->utc();

        foreach ($this->windows() as $window) {
            if ($now->gte($window['opens']) && $now->lt($window['closes'])) {
                return $window;
            }
        }

        return null;
    }

    /** The next window to open after $now, if any. */
    public function next(?CarbonInterface $now = null): ?array
    {
        $now = CarbonImmutable::instance($now ?? now())->utc();

        foreach ($this->windows() as $window) {
            if ($window['opens']->gt($now)) {
                return $window;
            }
        }

        return null;
    }

    /**
     * For the app: open or not, until when / from when — as moments and as
     * labels in the event's own time ("Sat 8:00 AM").
     *
     * @return array<string, mixed>
     */
    public function status(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now())->utc();
        $current = $this->current($now);
        $next = $current ? null : $this->next($now);
        $windows = $this->windows();

        return [
            'open' => $current !== null,
            'day' => $current['day'] ?? null,
            'day_number' => $current ? array_search($current['day'], array_column($windows, 'day'), true) + 1 : null,
            'days' => count($windows),
            'closes_at' => $current ? $current['closes']->toIso8601String() : null,
            'closes_label' => $current ? $this->label($current['closes'], $now) : null,
            'opens_at' => $next ? $next['opens']->toIso8601String() : null,
            'opens_label' => $next ? $this->label($next['opens'], $now) : null,
            'ended' => $current === null && $next === null && $windows !== [],
            'timezone' => $this->zone->getName(),
        ];
    }

    /** The local event day a moment falls on (for grouping messages). */
    public function localDay(CarbonInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone($this->zone)->toDateString();
    }

    /**
     * The event's days: its start to end date when known (capped), otherwise
     * the days its sessions are on.
     *
     * @param  array<int, string>  $sessionDays
     * @return array<int, string>
     */
    private function days(array $sessionDays): array
    {
        $start = $this->event->starts_on?->toDateString();
        $end = ($this->event->ends_on ?? $this->event->starts_on)?->toDateString();

        // A session on a later day means the event runs that long, whatever
        // its (often missing) end date says.
        if ($start !== null && $end !== null) {
            $limit = $this->event->starts_on->addDays(EventTime::MAX_EVENT_SPAN_DAYS)->toDateString();
            $within = array_filter($sessionDays, fn ($day) => $day <= $limit);

            if ($within !== []) {
                $end = max($end, max($within));
            }
        }

        if ($start === null) {
            sort($sessionDays);

            return array_slice($sessionDays, 0, self::MAX_DAYS);
        }

        $days = [];
        $cursor = CarbonImmutable::parse($start, $this->zone);
        $last = CarbonImmutable::parse(max($start, $end), $this->zone);

        while ($cursor->lte($last) && count($days) < self::MAX_DAYS) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /** "8:00 AM" today in the event's zone, "Sat 8:00 AM" on another day. */
    private function label(CarbonImmutable $moment, CarbonImmutable $now): string
    {
        $local = $moment->setTimezone($this->zone);
        $sameDay = $local->toDateString() === $now->setTimezone($this->zone)->toDateString();

        return $local->format($sameDay ? 'g:i A' : 'D j M, g:i A');
    }
}
