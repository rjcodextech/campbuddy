<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            ...self::eventsWithoutData(),
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
            'fix' => '* * * * * php '.base_path('artisan').' schedule:run >> /dev/null 2>&1   (one cPanel cron entry, every minute)',
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
}
