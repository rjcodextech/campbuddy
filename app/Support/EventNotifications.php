<?php

namespace App\Support;

use App\Jobs\SendAfterEventPushesJob;
use App\Jobs\SendThankYouPushJob;
use App\Models\Event;
use App\Models\PushSubscription;
use Carbon\CarbonImmutable;

/**
 * Every push CampBuddy sends about an event, for its Notifications tab (admin
 * and managers): what it says, when it goes, and — once sent — when and to how
 * many phones (events.push_log). Read only; the photo album and WordPress.tv
 * ones are sent from the Memories tab.
 */
class EventNotifications
{
    /** @return list<array{key: string, title: string, when: string, text: string, state: string, status: string}> */
    public static function rows(Event $event): array
    {
        $log = is_array($event->push_log) ? $event->push_log : [];
        $zone = EventTime::zone($event);
        $last = EventTime::lastDay($event);
        $days = EventTime::daysSinceEnd($event);
        $hours = sprintf('%02d:00–%02d:00', SendThankYouPushJob::FROM_HOUR, SendThankYouPushJob::UNTIL_HOUR);

        $after = function (string $key, string $title, int $day, string $text, $sentAt) use ($log, $last, $days, $zone, $hours) {
            $sent = $log[$key] ?? null;
            $date = $last ? CarbonImmutable::parse($last, $zone)->addDays($day) : null;
            [$state, $status] = match (true) {
                $sent !== null => ['sent', 'Sent '.self::when($sent['at'], $zone).' to '.self::phones($sent['phones'])],
                $sentAt !== null => ['sent', 'Sent '.self::when($sentAt, $zone)],
                $date === null => ['waiting', 'Waits for the event\'s dates'],
                $days !== null && $days > EventTime::RETENTION_DAYS => ['missed', 'Not sent: the event ended over '.EventTime::RETENTION_DAYS.' days ago'],
                default => ['scheduled', 'Goes on '.$date->format('D j M').", between {$hours} at the venue"],
            };

            return ['key' => $key, 'title' => $title, 'when' => "Day {$day} after the event", 'text' => $text, 'state' => $state, 'status' => $status];
        };

        $memory = function (string $key, string $title, string $text, $sentAt, ?string $url) use ($log, $zone) {
            $sent = $log[$key] ?? null;
            [$state, $status] = match (true) {
                $sent !== null => ['sent', 'Sent '.self::when($sent['at'], $zone).' to '.self::phones($sent['phones'])],
                $sentAt !== null => ['sent', 'Sent '.self::when($sentAt, $zone)],
                $url !== null => ['ready', 'Link added: send it from the Memories tab'],
                default => ['waiting', 'Add the link in the Memories tab, then send it'],
            };

            return ['key' => $key, 'title' => $title, 'when' => 'When you send it', 'text' => $text, 'state' => $state, 'status' => $status];
        };

        return [
            [
                'key' => 'reminders', 'title' => 'Session reminders', 'when' => '5–10 minutes before each saved session',
                'text' => 'To each phone, for the sessions it saved with the reminder on.', 'state' => 'auto', 'status' => 'Automatic while the event is on',
            ],
            $after('thank-you', 'Thank you', EventTime::THANK_YOU_AFTER_DAYS, 'Thanks for coming: rate it in one tap, and save your day as a PDF.', $event->thank_you_sent_at),
            $after('save-data', 'Keep your WordCamp', SendAfterEventPushesJob::SAVE_AFTER_DAYS, 'Your sessions, the people you met and your notes live only on this phone. Save them as a PDF in one tap.', $event->save_data_sent_at ?? null),
            $after('move-data', 'Take it with you', SendAfterEventPushesJob::MOVE_AFTER_DAYS, 'Move CampBuddy to a new phone, or bring your profile and the people you met to your next WordCamp.', $event->move_data_sent_at ?? null),
            $memory('photos', 'Photo album', 'The official photos are up: find yourself in them.', $event->photo_album_notified_at ?? null, $event->photo_album_url ?? null),
            $memory('videos', 'WordPress.tv speaker sessions', 'Missed a talk? The recordings are on WordPress.tv.', $event->wordpress_tv_notified_at ?? null, $event->wordpress_tv_url ?? null),
        ];
    }

    /** Phones with reminders on for the event: who every push here reaches. */
    public static function phones(int|Event $count): string
    {
        $n = $count instanceof Event ? PushSubscription::where('event_id', $count->id)->count() : $count;

        return number_format($n).' '.($n === 1 ? 'phone' : 'phones');
    }

    private static function when($at, \DateTimeZone $zone): string
    {
        return CarbonImmutable::parse($at)->setTimezone($zone)->format('D j M, g:i A');
    }
}
