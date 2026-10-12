<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Throwable;

/**
 * What runs by itself (routes/console.php, through the one cron entry that
 * runs `schedule:run` every minute), for Admin → Commands: each task's name,
 * what it does, how often and when it runs next — read from the schedule, so
 * the page can't drift from it. Only the wording of WHAT is written here.
 */
class ScheduledTasks
{
    /** schedule name => what it does, in a line. */
    public const WHAT = [
        'ingest-sessions-speakers-sponsors' => 'Fetches the schedule, speakers and sponsors of every live event from its site (for a week after it ends).',
        'ingest-event-info' => 'Fetches each live event\'s information (venue, links, contact); what an admin typed is never overwritten.',
        'ingest-attendee-roster' => 'Reads each live event\'s public Attendees page: new people in, people who left out.',
        'backfill-event-branding' => 'Fetches a logo or favicon that is still missing for a live event.',
        'heartbeat' => 'Writes "the scheduler ran", which the dashboard checks.',
        'send-session-reminders' => 'Sends the "starting soon" push 5–10 minutes before each session a phone saved.',
        'send-thank-you-push' => 'Day 3 after an event: the thank-you push (rating, save as PDF), once, in the venue\'s daytime.',
        'send-after-event-pushes' => 'Day 7 "Keep your WordCamp" and day 15 "Take it with you" pushes, once each, in the venue\'s daytime.',
        'discover-wordcamps' => 'Finds new in-person WordCamps on events.wordpress.org and adds them as drafts.',
        'evaluate-event-lifecycle' => 'Publishes ready drafts, and archives events three months after they end.',
        'prune-fetch-log' => 'Removes fetch-log rows older than 7 days (the latest of each kind stays).',
        'clear-social-cache' => 'Removes Social media copies of logos, artwork and fonts no event uses any more.',
        'indexnow' => 'Tells search engines about new and changed pages (live site only).',
        'prune-device-transfers' => 'Deletes finished or expired "move to this device" requests.',
        'work-queue' => 'Works off the queue (fetches, pushes) that the tasks above put on it.',
    ];

    /**
     * @return list<array{name: string, what: string, when: string, next: ?CarbonImmutable, command: ?string}>
     */
    public static function all(): array
    {
        try {
            // The schedule is defined with the console routes: load them if this is a web request.
            app(Kernel::class)->all();
            $events = app(Schedule::class)->events();
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $commands = array_keys(app(Kernel::class)->all());

        return collect($events)->map(function (ScheduledEvent $event) use ($commands) {
            $name = (string) ($event->description ?? '');
            $command = in_array('campbuddy:'.$name, $commands, true) ? 'campbuddy:'.$name : null;
            try {
                $next = CarbonImmutable::instance($event->nextRunDate())->setTimezone(config('app.timezone'));
            } catch (Throwable) {
                $next = null;
            }

            return ['name' => $name, 'what' => self::WHAT[$name] ?? '', 'when' => self::cadence($event->expression), 'next' => $next, 'command' => $command];
        })->values()->all();
    }

    /** The task for a command, if the scheduler runs it (same name after "campbuddy:"). */
    public static function forCommand(string $command): ?array
    {
        return collect(self::all())->firstWhere('command', $command);
    }

    /** A cron expression in words ("every 15 minutes", "every day at 03:20 UTC"); the expression itself when it is not one of the usual shapes. */
    public static function cadence(string $expression): string
    {
        $tz = config('app.timezone');
        $at = fn (string $h, string $m) => sprintf('%02d:%02d', (int) $h, (int) $m).' '.$tz;
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) {
            return $expression;
        }
        [$m, $h, $dom, $mon, $dow] = $parts;

        return match (true) {
            $expression === '* * * * *' => 'every minute',
            preg_match('#^\*/(\d+)$#', $m, $x) === 1 && $h === '*' && $dom === '*' => "every {$x[1]} minutes",
            ctype_digit($m) && $h === '*' && $dom === '*' => sprintf('every hour at :%02d', (int) $m),
            ctype_digit($m) && ctype_digit($h) && $dom === '*' && $mon === '*' && $dow === '*' => 'every day at '.$at($h, $m),
            // */2 in the day-of-month field: the even dates, so a gap of one or two days at a month's end.
            ctype_digit($m) && ctype_digit($h) && $dom === '*/2' => 'every other day (even dates) at '.$at($h, $m),
            ctype_digit($m) && ctype_digit($h) && preg_match('#^\*/(\d+)$#', $dom, $x) === 1 => "every {$x[1]} days at ".$at($h, $m),
            default => $expression,
        };
    }
}
