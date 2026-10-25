<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One command that brings CampBuddy up to date right now, from the server's
 * terminal (Admin → Commands lists it as terminal-only):
 *
 *   php artisan campbuddy:run-all
 *
 * 1. clears every cache and optimisation (and caches them again on a live site);
 * 2. runs every pending database migration;
 * 3. runs every scheduled task (routes/console.php) once, now, whatever its
 *    time: the fetches, pushes, clean-ups and the queue drain;
 * 4. works the queue off, waiting (up to --wait seconds) for the jobs the
 *    fetches queue a few seconds apart, and says what is left.
 *
 * Running the push tasks early sends nothing extra: each checks for itself
 * whether a push is due (the session's window, the venue's daytime) and
 * marks it sent, so a push still goes once. A task another run is busy with
 * (withoutOverlapping) is skipped, not run twice.
 */
class RunAllCommand extends Command
{
    protected $signature = 'campbuddy:run-all
        {--wait=600 : Seconds to keep working the queue for jobs queued to run a little later (0: don\'t wait)}';

    protected $description = 'Clear caches, run pending migrations, run every scheduled task now and work the queue off';

    public function handle(): int
    {
        $ok = true;
        @set_time_limit(0);
        $started = now();

        // 1. Caches first: stale config or routes would run the rest against the old code.
        $this->components->info('1/4 Clearing caches and optimisations');
        $this->callSilently('optimize:clear');
        $this->components->twoColumnDetail('Application cache, config, routes, views, events, compiled', '<fg=green>cleared</>');

        // 2. Database.
        $this->components->info('2/4 Database migrations');
        try {
            $this->call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            $this->components->error('Migrations failed: '.$e->getMessage());
            $this->line('  Nothing after this was run. Fix the error above, then run this command again.');

            return self::FAILURE;
        }

        // Cached again on a live site only: cached config on a dev machine would hide every later .env change.
        if (app()->isProduction()) {
            try {
                $this->callSilently('optimize');
                $this->components->twoColumnDetail('Config, routes, views, events', '<fg=green>cached again</>');
            } catch (Throwable $e) {
                $this->callSilently('optimize:clear');
                $this->components->warn('Couldn\'t cache ('.$e->getMessage().') — running uncached, which is fine.');
            }
        }

        // 3. Every scheduled task, once, in the schedule's own order (the queue drain is last).
        $this->components->info('3/4 Every scheduled task, now');
        foreach (app(Schedule::class)->events() as $event) {
            $ok = $this->runTask($event) && $ok;
        }

        // 4. What the tasks queued.
        $this->components->info('4/4 Queue');
        $ok = $this->workQueue(max(0, (int) $this->option('wait')), $started) && $ok;

        $ok ? $this->components->info('All done.') : $this->components->warn('Done, with the problems above.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function runTask(ScheduledEvent $event): bool
    {
        $name = $event->description ?: $event->getSummaryForDisplay();
        if (! $event->filtersPass($this->laravel)) {
            $this->components->twoColumnDetail($name, '<fg=gray>skipped (not used with this setup)</>');

            return true;
        }

        $event->exitCode = null;
        $start = microtime(true);
        try {
            $event->run($this->laravel);
        } catch (Throwable $e) {
            report($e);
            $this->components->twoColumnDetail($name, '<fg=red>failed</>');
            $this->line('    <fg=red>'.e(str($e->getMessage())->squish()->limit(200)).'</>');

            return false;
        }

        // run() returns without running when another run holds the task's lock (exitCode stays unset).
        $status = match (true) {
            $event->exitCode === null => '<fg=yellow>skipped (already running elsewhere)</>',
            $event->exitCode !== 0 => '<fg=red>failed</>',
            default => '<fg=green>done</> <fg=gray>'.number_format(microtime(true) - $start, 1).' s</>',
        };
        $this->components->twoColumnDetail($name, $status);

        return $event->exitCode === null || $event->exitCode === 0;
    }

    /** Works the queue until it is empty, waiting up to $wait seconds for jobs queued to run a little later. */
    private function workQueue(int $wait, $started): bool
    {
        $driver = config('queue.default');
        if ($driver === 'sync') {
            $this->components->twoColumnDetail('Jobs ran as they were queued (QUEUE_CONNECTION=sync)', '<fg=green>done</>');

            return true;
        }

        // The worker stops by itself once this long-lived process passes its memory limit (128 MB by
        // default), after a single job; a higher limit, and working it again while jobs are ready, finish it.
        $work = fn (int $seconds) => Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => max(60, $seconds), '--tries' => 3, '--memory' => 1024]);
        if (config("queue.connections.{$driver}.driver") !== 'database') {
            $work(50);
            $this->components->twoColumnDetail('Queue worked off (what was ready)', '<fg=green>done</>');

            return true;
        }

        $jobs = DB::connection(config("queue.connections.{$driver}.connection"))->table(config("queue.connections.{$driver}.table", 'jobs'));
        $ready = fn () => (clone $jobs)->whereNull('reserved_at')->where('available_at', '<=', time())->count();
        $until = time() + $wait;
        // Jobs that are ready are always worked off, for at most this long, whatever --wait says.
        $stop = max($until, time() + 900);
        $said = false;
        $stuck = 0;
        while (time() < $stop) {
            if (($before = [$ready(), (clone $jobs)->count()])[0] > 0) {
                $work($stop - time());
                // Three passes in a row that change nothing: a job the worker can't take; leave it to the cron.
                $stuck = [$ready(), (clone $jobs)->count()] === $before ? $stuck + 1 : 0;
                if ($stuck >= 3) {
                    break;
                }

                continue;
            }
            $next = (clone $jobs)->whereNull('reserved_at')->min('available_at');
            if ($next === null || $next > $until || time() >= $until) {
                break;
            }
            if (! $said) {
                $this->line('  Waiting for jobs queued to run a little later (up to '.$wait.' s; --wait=0 to skip)…');
                $said = true;
            }
            sleep(max(1, min((int) $next - time(), $until - time())));
        }

        $left = (clone $jobs)->count();
        $failed = 0;
        try {
            $failed = DB::table(config('queue.failed.table', 'failed_jobs'))->where('failed_at', '>=', $started)->count();
        } catch (Throwable) {
            // No failed-jobs table: nothing to count.
        }

        $this->components->twoColumnDetail('Jobs still waiting', $left === 0 ? '<fg=green>none</>' : "<fg=yellow>{$left}</> <fg=gray>(the cron works them off)</>");
        $this->components->twoColumnDetail('Jobs that failed in this run', $failed === 0 ? '<fg=green>none</>' : "<fg=red>{$failed}</> <fg=gray>(php artisan queue:failed)</>");

        return $failed === 0;
    }
}
