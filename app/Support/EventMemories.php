<?php

namespace App\Support;

use App\Jobs\SendMemoriesPushJob;
use App\Models\Event;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Memories: after a WordCamp, the official photo album and its talks on
 * WordPress.tv. An admin or the event's managers add the links (Event →
 * Memories), then send one push per link to every phone that turned
 * reminders on for the event — once per kind, never again. The push opens
 * /event/{slug}/memories, which stays open after the event is archived
 * (WordPress.tv videos often arrive weeks later), so fixing a link after the
 * push still reaches everyone who taps it.
 */
class EventMemories
{
    /** kind => its columns and words. */
    public const KINDS = [
        'photos' => [
            'url' => 'photo_album_url',
            'notified' => 'photo_album_notified_at',
            'label' => 'Photo album',
            'hint' => 'The official photos: Google Photos, Flickr, WordPress Photo Directory…',
        ],
        'videos' => [
            'url' => 'wordpress_tv_url',
            'notified' => 'wordpress_tv_notified_at',
            'label' => 'WordPress.tv speaker sessions',
            'hint' => 'The event\'s page on wordpress.tv, e.g. https://wordpress.tv/event/wordcamp-…',
        ],
    ];

    /** @return array<string, string> kind => link, only the ones set */
    public static function links(Event $event): array
    {
        $links = [];
        foreach (self::KINDS as $kind => $meta) {
            if ($url = SafeUrl::web($event->{$meta['url']})) {
                $links[$kind] = $url;
            }
        }

        return $links;
    }

    /** Whether this event's memories page may be shown: live, or archived but still visible. */
    public static function pageIsPublic(Event $event): bool
    {
        return $event->is_visible && in_array($event->status, ['active', 'archived'], true);
    }

    /**
     * Validates and saves both links (an empty field clears that link).
     *
     * @return string what changed, for the status line and the managers' activity log
     */
    public static function save(Request $request, Event $event): string
    {
        $data = $request->validate([
            'photo_album_url' => ['nullable', 'string', 'max:500', 'url:https'],
            'wordpress_tv_url' => ['nullable', 'string', 'max:500', 'url:https', function ($attribute, $value, $fail) {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                if ($host !== 'wordpress.tv' && ! str_ends_with($host, '.wordpress.tv')) {
                    $fail('This must be a wordpress.tv link.');
                }
            }],
        ], [
            'photo_album_url.url' => 'Use the album\'s full https:// link.',
            'wordpress_tv_url.url' => 'Use the full https://wordpress.tv/… link.',
        ]);

        $changes = [];
        foreach (self::KINDS as $meta) {
            $new = isset($data[$meta['url']]) ? trim($data[$meta['url']]) : null;
            $new = $new === '' ? null : $new;
            if ($new !== $event->{$meta['url']}) {
                $changes[] = $meta['label'].($new ? ' set to '.$new : ' removed');
                $event->{$meta['url']} = $new;
            }
        }

        if ($changes === []) {
            return 'Nothing changed';
        }

        $event->save();

        return implode('; ', $changes);
    }

    /** The link is there and its push hasn't gone out yet. */
    public static function canNotify(Event $event, string $kind): bool
    {
        $meta = self::KINDS[$kind] ?? null;

        return $meta !== null && SafeUrl::web($event->{$meta['url']}) !== null && $event->{$meta['notified']} === null;
    }

    /** Phones that would get the push. */
    public static function recipients(Event $event): int
    {
        return PushSubscription::where('event_id', $event->id)->count();
    }

    /**
     * Queues this kind's push, once: the "sent" time is claimed in one
     * conditional update, so a double click or two people pressing it at
     * once still sends a single notification.
     */
    public static function notify(Event $event, string $kind): void
    {
        if (! self::canNotify($event, $kind)) {
            throw ValidationException::withMessages(['notify' => 'This notification was already sent, or its link is missing.']);
        }

        $column = self::KINDS[$kind]['notified'];
        $claimed = Event::whereKey($event->id)->whereNull($column)->update([$column => now()]);

        if ($claimed !== 1) {
            throw ValidationException::withMessages(['notify' => 'This notification was already sent.']);
        }

        $event->refresh();
        SendMemoriesPushJob::dispatch($event->id, $kind);
    }
}
