<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The things that silently break a live CampBuddy: the cron that runs the
 * scheduler not running, database migrations not run after a deploy, jobs
 * piling up or failing, a live event with no data. Each problem comes with
 * what to do about it, for the admin dashboard (and a yes/no summary for
 * the public health endpoint).
 */
class SystemHealth
{
    /** routes/console.php's "heartbeat" writes this every minute the scheduler runs. */
    public const HEARTBEAT_KEY = 'health:scheduler-at';

    /**
     * @return array<int, array{level: string, title: string, detail: string, fix: ?string}>
     */
    public static function problems(): array
    {
        return array_values(array_filter([
            self::pendingMigrations(),
            self::scheduler(),
            self::queueBacklog(),
            self::failedJobs(),
            self::eventManagerSetup(),
            ...self::eventsWithoutData(),
            ...self::eventsWithoutTimezone(),
        ]));
    }

    /**
     * Yes/no per check, for /api/v1/health — no details on a public URL.
     *
     * @return array<string, bool>
     */
    public static function checks(): array
    {
        return [
            'migrations' => self::pendingMigrations() === null,
            'scheduler' => self::scheduler() === null,
            'queue' => self::queueBacklog() === null && self::failedJobs() === null,
        ];
    }

    private static function pendingMigrations(): ?array
    {
        try {
            $migrator = app('migrator');
            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
            $ran = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff($files, $ran));
        } catch (Throwable) {
            return null;
        }

        if ($pending === []) {
            return null;
        }

        return [
            'level' => 'error',
            'title' => count($pending).' database migration(s) not run',
            'detail' => 'The code is newer than the database, so some features fail (for example the attendee list and discovery). Pending: '.implode(', ', array_slice($pending, 0, 3)).(count($pending) > 3 ? '…' : ''),
            'fix' => 'php artisan migrate --force',
        ];
    }

    private static function scheduler(): ?array
    {
        $last = Cache::get(self::HEARTBEAT_KEY);

        if ($last && Carbon::parse($last)->gt(now()->subMinutes(5))) {
            return null;
        }

        return [
            'level' => 'error',
            'title' => $last ? 'The scheduler stopped running '.Carbon::parse($last)->diffForHumans() : 'The scheduler has never run',
            'detail' => 'Without it nothing is refreshed on schedule: sessions, sponsors, the attendee list, reminders. Event pages now fetch missing or stale data themselves, but the cron job is still needed.',
            'fix' => 'cPanel → Cron Jobs → Common Settings: "Once Per Minute (* * * * *)", and in Command put only: '.self::cronCommand(),
        ];
    }

    private static function queueBacklog(): ?array
    {
        if (config('queue.default') !== 'database' || ! Schema::hasTable('jobs')) {
            return null;
        }

        try {
            $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
            $count = DB::table('jobs')->count();
        } catch (Throwable) {
            return null;
        }

        if (! $oldest || $oldest > now()->subMinutes(10)->getTimestamp()) {
            return null;
        }

        return [
            'level' => 'warning',
            'title' => "{$count} background job(s) waiting",
            'detail' => 'The oldest has waited since '.Carbon::createFromTimestamp($oldest)->diffForHumans().'. Jobs are worked off by the scheduler — this usually means the cron isn\'t running.',
            'fix' => 'php artisan queue:work --stop-when-empty',
        ];
    }

    private static function failedJobs(): ?array
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return null;
            }
            $recent = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        } catch (Throwable) {
            return null;
        }

        if ($recent === 0) {
            return null;
        }

        return [
            'level' => 'warning',
            'title' => "{$recent} background job(s) failed in the last 24 hours",
            'detail' => 'Usually a WordCamp site that was down or slow. The event\'s "Data health" card shows which fetch failed and why.',
            'fix' => 'php artisan queue:failed   (to list them)   ·   php artisan queue:retry all',
        ];
    }

    /**
     * Event managers sign in through a guard and routes that came with the
     * Sept 2026 update. Code uploaded on top of an old *cached* config or route
     * list has neither, so /manager fails or 404s while everything else works —
     * easy to miss, and `optimize:clear && optimize` fixes it. (The tables are
     * the migration check's business.)
     *
     * @return array{level: string, title: string, detail: string, fix: ?string}|null
     */
    private static function eventManagerSetup(): ?array
    {
        $guard = config('auth.guards.manager') !== null && config('auth.providers.event_managers') !== null;
        $routes = Route::has('manager.login');

        if ($guard && $routes) {
            return null;
        }

        return [
            'level' => 'warning',
            'title' => 'Event manager sign-in isn\'t set up',
            'detail' => 'The code has it, but this server is still using a cached '.($guard ? 'route list' : 'config').' from before the update, so /manager can\'t work yet. Nothing else is affected.',
            'fix' => 'php artisan optimize:clear && php artisan optimize',
        ];
    }

    /**
     * @return array<int, array>
     */
    private static function eventsWithoutData(): array
    {
        try {
            $events = Event::where('status', 'active')->where('is_visible', true)->get(['id', 'display_name', 'slug']);
        } catch (Throwable) {
            return [];
        }

        return $events
            ->filter(fn (Event $event) => EventData::get($event->id, 'sessions') === null)
            ->map(fn (Event $event) => [
                'level' => 'warning',
                'title' => "{$event->display_name} has no schedule data yet",
                'detail' => 'Attendees see an empty schedule and no sponsors. Open the event and use "Refresh now" — its Data health card shows what the WordCamp site answered.',
                'fix' => 'php artisan campbuddy:ingest '.$event->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * Live events whose time zone isn't known: their session times, days and
     * discovery chat hours would be read in the server's zone instead.
     *
     * @return array<int, array>
     */
    private static function eventsWithoutTimezone(): array
    {
        try {
            $events = Event::where('status', 'active')->where('is_visible', true)->get(['id', 'display_name', 'slug', 'timezone']);
        } catch (Throwable) {
            return [];
        }

        return $events
            ->reject(fn (Event $event) => EventTime::known($event))
            ->map(fn (Event $event) => [
                'level' => 'warning',
                'title' => "{$event->display_name} has no time zone",
                'detail' => 'Session times and the discovery chat hours use it. "Refresh now" reads it from the WordCamp site; or type it on the event page (e.g. Asia/Kolkata).',
                'fix' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * The exact cron command for this server: the full path of the PHP that
     * runs artisan here (cron's plain "php" is often a different, older one),
     * then the scheduler. Only the command — cPanel asks for the timing in
     * separate fields, and "* * * * *" pasted into the command is rejected.
     */
    public static function cronCommand(): string
    {
        $php = PHP_BINARY;

        // Asked from a web request, PHP_BINARY can be the FPM/CGI server — not
        // something cron can run.
        if ($php === '' || preg_match('/fpm|cgi|httpd|apache/i', basename($php))) {
            $php = 'php';
        }

        return $php.' '.base_path('artisan').' schedule:run >> /dev/null 2>&1';
    }
}
