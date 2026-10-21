<?php

namespace App\Support;

use App\Jobs\SendBroadcastPushJob;
use App\Models\Event;
use App\Models\NativePushToken;
use App\Models\PushSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Live announcements an admin or an event's manager sends to everyone at the
 * WordCamp: "Opening remarks are starting", "Tea break", "Lunch is served",
 * a session about to start… Each goes as a push to every phone and browser
 * that had the event open since three days before it (and allowed
 * notifications), and shows as a banner on Home to anyone who opens the event
 * in the next half hour — so people without notifications see it too.
 */
class EventBroadcasts
{
    /** Devices that opened the event from this many days before its first day get the push. */
    public const WINDOW_DAYS = 3;

    /** How long the banner shows after sending. */
    public const BANNER_MINUTES = 30;

    /** At most one every this many seconds per event (a double tap, two organizers at once). */
    public const GAP_SECONDS = 60;

    /** And at most this many a day per event. */
    public const PER_DAY = 40;

    /**
     * The ready-made announcements: kind => [label, icon, title, body, tab]. {where} and {session} are filled in.
 * The title starts with an emoji, so the push itself says what is happening at a glance.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: ?string}>
     */
    public const PRESETS = [
        'registration' => ['Registration open', 'ticket', '🎟️ Registration is open', 'Collect your badge and swag at the registration desk{where}.', null],
        'opening' => ['Opening remarks', 'mic', '🎤 Opening remarks are starting', 'Find a seat{where}: the day begins now.', 'my-day'],
        'keynote' => ['Keynote', 'star', '⭐ The keynote starts in 5 minutes', 'Find a seat{where} now so you don\'t miss the start.', 'my-day'],
        'session' => ['Session starting', 'calendar', '⏰ {session} starts in 5 minutes', 'Starting soon{where}. See you there!', 'my-day'],
        'panel' => ['Panel discussion', 'users', '💬 The panel discussion is starting', 'Join us{where} and bring your questions.', 'my-day'],
        'tea' => ['Tea break', 'coffee', '☕ Tea break!', 'Tea and snacks are out{where}. Back to the sessions in 15 minutes.', null],
        'break' => ['Short break', 'clock', '🚶 Short break', 'Stretch your legs, meet someone new{where}. We start again in 10 minutes.', null],
        'lunch' => ['Lunch', 'utensils', '🍽️ Lunch is served', 'Lunch is ready{where}. Sessions start again after the break.', null],
        'high_tea' => ['High tea', 'coffee', '🫖 High tea is served', 'Tea and snacks{where}: a good time to say hi to a speaker.', null],
        'resume' => ['Sessions resume', 'calendar', '🔔 Sessions are starting again', 'Please take your seats{where}: the next sessions begin in 5 minutes.', 'my-day'],
        'photo' => ['Group photo', 'camera', '📸 Group photo in 5 minutes', 'Come along{where} for the WordCamp group photo.', null],
        'contributor' => ['Contributor Day', 'wrench', '🛠️ Contributor Day tables are open', 'Find your team\'s table{where}. Everyone is welcome, no coding needed.', 'contribute'],
        'closing' => ['Closing remarks', 'heart', '🎉 Closing remarks are starting', 'Join us{where} as we wrap up the day. Thank you for coming!', null],
        'custom' => ['Your own message', 'megaphone', '', '', null],
    ];

    /** The banner's colour: talks (the brand colour), breaks and food (gold), together moments (teal). */
    public const TONES = [
        'tea' => 'break', 'break' => 'break', 'lunch' => 'break', 'high_tea' => 'break',
        'photo' => 'together', 'contributor' => 'together', 'closing' => 'together',
    ];

    public const TABS = ['my-day', 'quest', 'contribute', 'explore', 'camp-card'];

    /** The emoji a ready-made title starts with (📣 for your own message). */
    public static function emoji(string $title): string
    {
        return preg_match('/^(\S+)\s/u', $title, $m) === 1 && preg_match('/[\p{L}\p{N}{]/u', $m[1]) !== 1 ? $m[1] : '📣';
    }

    public static function tone(?string $kind): string
    {
        return self::TONES[$kind] ?? 'talk';
    }

    public static function ready(): bool
    {
        return Schema::hasTable('event_broadcasts') && Schema::hasTable('event_device_visits');
    }

    /** "This device has the event open" — at most one write an hour per device. */
    public static function seen(Event $event, string $deviceId, string $platform): void
    {
        if (! self::ready()) {
            return;
        }
        $now = now();
        $row = DB::table('event_device_visits')->where('event_id', $event->id)->where('device_id', $deviceId)->first(['id', 'last_seen_at']);

        if ($row === null) {
            DB::table('event_device_visits')->insertOrIgnore([
                'event_id' => $event->id, 'device_id' => $deviceId, 'platform' => $platform, 'first_seen_at' => $now, 'last_seen_at' => $now,
            ]);
        } elseif (CarbonImmutable::parse($row->last_seen_at)->lt($now->copy()->subHour())) {
            DB::table('event_device_visits')->where('id', $row->id)->update(['last_seen_at' => $now, 'platform' => $platform]);
        }
    }

    /** Notifications switched off on this device: its visit is no longer needed. */
    public static function forget(Event $event, string $deviceId): void
    {
        if (self::ready()) {
            DB::table('event_device_visits')->where('event_id', $event->id)->where('device_id', $deviceId)->delete();
        }
    }

    /** The moment the window opens: midnight at the venue, three days before the first day (or three days ago without dates). */
    public static function windowStart(Event $event): CarbonImmutable
    {
        $first = EventTime::firstDay($event) ?? $event->starts_on?->toDateString();

        return $first
            ? CarbonImmutable::parse($first, EventTime::zone($event))->subDays(self::WINDOW_DAYS)->startOfDay()->utc()
            : CarbonImmutable::now()->subDays(self::WINDOW_DAYS);
    }

    /** Device ids that had the event open since the window opened. */
    public static function audience(Event $event): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return DB::table('event_device_visits')->where('event_id', $event->id)->where('last_seen_at', '>=', self::windowStart($event))->pluck('device_id');
    }

    /**
     * Who would get the push: browsers (Web Push) and app installs whose device opened the event in the window —
     * or that turned notifications on for it in the window (which can only be done with the event open).
     *
     * @return array{web: int, app: int, seen: int}
     */
    public static function reach(Event $event): array
    {
        $since = self::windowStart($event);
        $devices = self::audience($event);

        return [
            'web' => self::webQuery($event, $devices, $since)->count(),
            'app' => self::appQuery($event, $devices, $since)->count(),
            'seen' => $devices->count(),
        ];
    }

    public static function webQuery(Event $event, Collection $devices, CarbonImmutable $since)
    {
        return PushSubscription::where('event_id', $event->id)->where(fn ($q) => $q->whereIn('device_id', $devices)->orWhere('updated_at', '>=', $since));
    }

    public static function appQuery(Event $event, Collection $devices, CarbonImmutable $since)
    {
        return NativePushToken::where('event_id', $event->id)->where(fn ($q) => $q->whereIn('device_id', $devices)->orWhere('updated_at', '>=', $since));
    }

    /**
     * The announcement as it will read: a preset's words with {where} / {session} filled in, or the person's own.
     *
     * @return array{title: string, body: string, tab: ?string}
     */
    public static function compose(string $kind, ?string $title, ?string $body, ?string $where, ?string $session, ?string $tab): array
    {
        [, , $presetTitle, $presetBody, $presetTab] = self::PRESETS[$kind];
        $fill = fn (string $text) => trim(str_replace(['{where}', '{session}'], [$where ? ' in '.$where : '', $session ?: 'The next session'], $text));

        return [
            'title' => mb_substr(trim((string) $title) !== '' ? trim($title) : $fill($presetTitle), 0, 80),
            'body' => mb_substr(trim((string) $body) !== '' ? trim($body) : $fill($presetBody), 0, 240),
            'tab' => in_array($tab, self::TABS, true) ? $tab : $presetTab,
        ];
    }

    /** Records it and queues the push. Returns the stored row's id. */
    public static function send(Event $event, string $kind, array $message, string $by): int
    {
        if (! self::ready()) {
            throw ValidationException::withMessages(['broadcast' => 'Live announcements need `php artisan migrate` first.']);
        }
        if ($message['title'] === '' || $message['body'] === '') {
            throw ValidationException::withMessages(['title' => 'Write a title and a message.']);
        }
        $recent = DB::table('event_broadcasts')->where('event_id', $event->id)->where('created_at', '>=', now()->subSeconds(self::GAP_SECONDS))->exists();
        if ($recent) {
            throw ValidationException::withMessages(['broadcast' => 'An announcement went out less than a minute ago. Wait a moment before sending another.']);
        }
        if (DB::table('event_broadcasts')->where('event_id', $event->id)->where('created_at', '>=', now()->subDay())->count() >= self::PER_DAY) {
            throw ValidationException::withMessages(['broadcast' => 'That is '.self::PER_DAY.' announcements in a day for this WordCamp, the most allowed.']);
        }

        $id = DB::table('event_broadcasts')->insertGetId([
            'event_id' => $event->id, 'kind' => $kind, 'title' => $message['title'], 'body' => $message['body'], 'tab' => $message['tab'],
            'sent_by' => mb_substr($by, 0, 120), 'recipients' => 0, 'show_until' => now()->addMinutes(self::BANNER_MINUTES), 'created_at' => now(), 'updated_at' => now(),
        ]);
        SendBroadcastPushJob::dispatch($id);
        \Illuminate\Support\Facades\Cache::forget("event:{$event->id}:live-notice");

        return $id;
    }

    /** The banner to show now, if an announcement went out in the last half hour. */
    public static function current(Event $event): ?array
    {
        if (! self::ready()) {
            return null;
        }
        $row = DB::table('event_broadcasts')->where('event_id', $event->id)->where('show_until', '>', now())->orderByDesc('id')->first();

        return $row ? self::shape($row) : null;
    }

    /** @return list<array<string, mixed>> the latest announcements, newest first */
    public static function history(Event $event, int $limit = 30): array
    {
        if (! self::ready()) {
            return [];
        }

        return DB::table('event_broadcasts')->where('event_id', $event->id)->orderByDesc('id')->limit($limit)->get()->map(fn ($r) => self::shape($r) + [
            'sent_by' => $r->sent_by, 'recipients' => (int) $r->recipients, 'kind' => $r->kind,
        ])->all();
    }

    /** @return list<array{key: string, label: string, icon: string, emoji: string, tone: string, title: string, body: string}> */
    public static function presets(): array
    {
        return collect(self::PRESETS)->map(fn ($p, $key) => ['key' => $key, 'label' => $p[0], 'icon' => $p[1], 'emoji' => self::emoji($p[2]), 'tone' => self::tone($key), 'title' => $p[2], 'body' => $p[3]])->values()->all();
    }

    /**
     * Sessions to pick for "Session starting": the ones starting from 15 minutes ago to 6 hours ahead
     * (all of them, in order, before the event has started), times at the venue.
     *
     * @return list<array{id: string, title: string, time: string, track: ?string}>
     */
    public static function upcomingSessions(Event $event): array
    {
        $zone = EventTime::zone($event);
        $now = CarbonImmutable::now();
        $all = collect(AppEventApi::sessions($event))
            ->filter(fn ($s) => ! empty($s['starts_at']) && ($s['title'] ?? '') !== '')
            ->map(fn ($s) => $s + ['at' => CarbonImmutable::parse($s['starts_at'])])
            ->sortBy('at')->values();
        $soon = $all->filter(fn ($s) => $s['at']->between($now->subMinutes(15), $now->addHours(6)))->values();

        return ($soon->isNotEmpty() ? $soon : $all->filter(fn ($s) => $s['at']->gt($now)))->take(40)->map(fn ($s) => [
            'id' => (string) $s['id'],
            'title' => HtmlText::plain((string) $s['title'], 120) ?? '',
            'time' => $s['at']->setTimezone($zone)->format('D j M, g:i A'),
            'track' => $s['track_names'][0] ?? null,
        ])->values()->all();
    }

    /** The send form's fields, checked: what an admin or a manager posts (web panel or app). */
    public static function fromRequest(\Illuminate\Http\Request $request, Event $event): array
    {
        $data = $request->validate([
            'kind' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(self::PRESETS))],
            'title' => ['nullable', 'string', 'max:80'],
            'body' => ['nullable', 'string', 'max:240'],
            'where' => ['nullable', 'string', 'max:60'],
            'session_id' => ['nullable', 'string', 'max:40'],
            'tab' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['', ...self::TABS])],
        ]);
        $session = null;
        if (($data['session_id'] ?? '') !== '') {
            $session = collect(AppEventApi::sessions($event))->first(fn ($s) => (string) ($s['id'] ?? '') === $data['session_id']);
            $session = $session ? HtmlText::plain((string) ($session['title'] ?? ''), 60) : null;
        }

        return [$data['kind'], self::compose($data['kind'], $data['title'] ?? null, $data['body'] ?? null, $data['where'] ?? null, $session, $data['tab'] ?? null)];
    }

    private static function shape(object $row): array
    {
        return [
            'id' => (int) $row->id, 'kind' => $row->kind, 'icon' => self::PRESETS[$row->kind][1] ?? 'megaphone', 'tone' => self::tone($row->kind),
            'title' => $row->title, 'body' => $row->body, 'tab' => $row->tab,
            'at' => CarbonImmutable::parse($row->created_at)->toIso8601String(),
        ];
    }
}
