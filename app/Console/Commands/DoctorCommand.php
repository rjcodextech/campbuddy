<?php

namespace App\Console\Commands;

use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Jobs\ParseAttendeeRosterJob;
use App\Models\Event;
use App\Support\SystemHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * One command to bring a CampBuddy install up to date and healthy — run it
 * after every deploy (and any time something looks wrong):
 *
 *   php artisan campbuddy:doctor
 *
 * 1. clears every cache and optimisation (config, routes, views, events,
 *    compiled, application cache);
 * 2. empties the log files;
 * 3. installs PHP dependencies if composer.lock changed;
 * 4. builds the front end (npm) where Node is available;
 * 5. runs every pending database migration;
 * 6. re-caches config/routes/views for speed (production);
 * 7. fetches every live event's data right now (schedule, speakers,
 *    sponsors, event information, missing branding, attendee list);
 * then reports anything still wrong — the cron job, failed jobs, debug mode
 * on a live site — with what to do about it.
 *
 * Steps that need a shell program (composer, npm) are skipped with a clear
 * note when the host doesn't allow running one — never a crash.
 */
class DoctorCommand extends Command
{
    protected $signature = 'campbuddy:doctor
        {--skip-fetch : Don\'t fetch event data}
        {--no-build : Don\'t run the front-end build (npm)}
        {--keep-logs : Don\'t empty storage/logs}';

    protected $description = 'After a deploy: run migrations, refresh caches, fetch every live event\'s data, and report problems';

    public function handle(): int
    {
        $ok = true;
        @set_time_limit(0);

        // 1. A clean slate: stale compiled config/routes/views and caches are
        //    the usual reason a fresh deploy still behaves like the old one.
        //    (Event data isn't lost: it's kept in the database, EventData.)
        $this->components->info('1/7 Clearing caches and optimisations');
        $this->callSilently('optimize:clear');
        $this->components->twoColumnDetail('Application cache, config, routes, views, events, compiled', '<fg=green>cleared</>');

        // 2. Logs.
        $this->components->info('2/7 Logs');
        if ($this->option('keep-logs')) {
            $this->components->twoColumnDetail('storage/logs', '<fg=gray>kept (--keep-logs)</>');
        } else {
            $this->components->twoColumnDetail('storage/logs', '<fg=green>'.$this->clearLogs().'</>');
        }

        // 3. PHP dependencies, if composer.lock changed since the last install.
        $this->components->info('3/7 PHP dependencies');
        $ok = $this->composerInstall() && $ok;

        // 4. Front-end build (CSS/JS).
        $this->components->info('4/7 Front-end build');
        if ($this->option('no-build')) {
            $this->components->twoColumnDetail('npm run build', '<fg=gray>skipped (--no-build)</>');
        } else {
            $ok = $this->frontendBuild() && $ok;
        }

        // 5. Database.
        $this->components->info('5/7 Database migrations');
        try {
            $this->call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            $this->components->error('Migrations failed: '.$e->getMessage());
            $this->line('  Nothing after this was run. Fix the error above, then run this command again.');

            return self::FAILURE;
        }

        // 6. Re-optimise for the new code (production only: cached config on a
        //    dev machine would hide every later .env change).
        $this->components->info('6/7 Optimise');
        if (! app()->isProduction()) {
            $this->components->twoColumnDetail('Config, routes, views, events', '<fg=gray>not cached outside production</>');
        } else {
            try {
                $this->callSilently('optimize');
                $this->components->twoColumnDetail('Config, routes, views, events', '<fg=green>cached</>');
            } catch (Throwable $e) {
                // Not caching is slower, never broken.
                $this->callSilently('optimize:clear');
                $this->components->warn('Couldn\'t cache ('.$e->getMessage().') — running uncached, which is fine.');
            }
        }

        if (! $this->option('skip-fetch')) {
            $this->components->info('7/7 Fetching live events\' data');
            $events = Event::whereIn('status', ['approved', 'active'])->orderBy('id')->get();

            if ($events->isEmpty()) {
                $this->line('  No approved or active events.');
            }

            foreach ($events as $event) {
                $this->line("  <options=bold>{$event->display_name}</> ({$event->slug})");
                $steps = [
                    'Event information' => ['event_info', fn () => FetchEventInfoJob::dispatchSync($event)],
                    'Logo & favicon' => ['branding', fn () => FetchBrandingAssetsJob::dispatchSync($event, onlyMissing: true)],
                ];
                if ($event->status === 'active') {
                    $steps['Schedule, speakers, sponsors'] = ['sessions_speakers_sponsors', fn () => FetchSpeakersSponsorsSessionsJob::dispatchSync($event)];
                    $steps['Attendee list'] = ['roster', fn () => ParseAttendeeRosterJob::dispatchSync($event)];
                }

                foreach ($steps as $label => [$jobType, $run]) {
                    $ok = $this->runStep($event, $label, $jobType, $run) && $ok;
                }
            }
        }

        $this->components->info('Health check');
        $problems = SystemHealth::problems();

        if (app()->isProduction() && config('app.debug')) {
            array_unshift($problems, [
                'level' => 'error',
                'title' => 'APP_DEBUG is on in production',
                'detail' => 'Error pages show visitors your code, database name and server details.',
                'fix' => 'Set APP_DEBUG=false in .env, then run this command again.',
            ]);
        }

        if ($problems === []) {
            $this->components->info('All good — nothing needs attention.');

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        foreach ($problems as $problem) {
            $colour = $problem['level'] === 'error' ? 'red' : 'yellow';
            $this->line("  <fg={$colour};options=bold>• {$problem['title']}</>");
            $this->line("    {$problem['detail']}");
            if ($problem['fix']) {
                $this->line("    <fg=cyan>Fix:</> {$problem['fix']}");
            }
        }

        return collect($problems)->contains('level', 'error') ? self::FAILURE : ($ok ? self::SUCCESS : self::FAILURE);
    }

    private function runStep(Event $event, string $label, string $jobType, callable $run): bool
    {
        $startedAt = now()->subSecond();

        try {
            $run();
        } catch (Throwable) {
            // The job has written why to the fetch log; shown below.
        }

        $log = $event->fetchLogs()->where('job_type', $jobType)->where('fetched_at', '>=', $startedAt)->latest('id')->first();

        $status = match ($log?->status) {
            'ok' => '<fg=green>ok</>',
            'partial' => '<fg=yellow>partly</>',
            null => '<fg=gray>nothing to do</>',
            default => '<fg=red>failed</>',
        };

        $this->components->twoColumnDetail("    {$label}".($log?->message ? " <fg=gray>— {$log->message}</>" : ''), $status);

        return ! in_array($log?->status, ['error'], true);
    }

    /** Empties every log file, keeping the files (and their permissions). */
    private function clearLogs(): string
    {
        $freed = 0;
        $files = glob(storage_path('logs/*.log')) ?: [];

        foreach ($files as $file) {
            $freed += (int) @filesize($file);
            @file_put_contents($file, '');
        }

        return count($files).' file(s) emptied, '.round($freed / 1048576, 1).' MB freed';
    }

    private function canRunPrograms(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && ! in_array('proc_open', $disabled, true);
    }

    private function composerInstall(): bool
    {
        $installed = base_path('vendor/composer/installed.json');
        $lock = base_path('composer.lock');

        if (is_file($installed) && is_file($lock) && filemtime($installed) >= filemtime($lock)) {
            $this->components->twoColumnDetail('composer install', '<fg=gray>up to date</>');

            return true;
        }

        $command = ['composer', 'install', '--no-interaction', '--prefer-dist', '--optimize-autoloader'];
        if (app()->isProduction()) {
            $command[] = '--no-dev';
        }

        return $this->runProgram('composer install', $command, 'Run it by hand: '.implode(' ', $command));
    }

    private function frontendBuild(): bool
    {
        if (! is_file(base_path('package.json'))) {
            return true;
        }

        $install = is_dir(base_path('node_modules')) ? null : (is_file(base_path('package-lock.json')) ? ['npm', 'ci'] : ['npm', 'install']);
        $hint = 'On hosting without Node, build on your computer (npm run build) and upload the public/build folder.';

        if ($install && ! $this->runProgram(implode(' ', $install), $install, $hint)) {
            return $this->builtAssetsExist();
        }

        return $this->runProgram('npm run build', ['npm', 'run', 'build'], $hint) || $this->builtAssetsExist();
    }

    /** A failed build is only a problem if there's no previous build to serve. */
    private function builtAssetsExist(): bool
    {
        if (is_file(public_path('build/manifest.json'))) {
            $this->line('    <fg=gray>The existing public/build is still in place, so the site keeps working.</>');

            return true;
        }

        $this->components->error('There is no public/build — pages can\'t load their styles until it\'s built.');

        return false;
    }

    /** @param array<int, string> $command */
    private function runProgram(string $label, array $command, string $hint): bool
    {
        if (! $this->canRunPrograms()) {
            $this->components->twoColumnDetail($label, '<fg=yellow>skipped — this host doesn\'t allow running programs from PHP</>');
            $this->line("    <fg=gray>{$hint}</>");

            return true;
        }

        try {
            $result = Process::path(base_path())->timeout(900)->run($command);
        } catch (Throwable $e) {
            $this->components->twoColumnDetail($label, '<fg=yellow>skipped — '.$e->getMessage().'</>');
            $this->line("    <fg=gray>{$hint}</>");

            return true;
        }

        if ($result->successful()) {
            $this->components->twoColumnDetail($label, '<fg=green>done</>');

            return true;
        }

        // "command not found" — the program isn't installed here.
        if ($result->exitCode() === 127) {
            $this->components->twoColumnDetail($label, '<fg=yellow>skipped — not installed on this server</>');
            $this->line("    <fg=gray>{$hint}</>");

            return true;
        }

        $this->components->twoColumnDetail($label, '<fg=red>failed</>');
        $this->line('    '.trim(mb_substr($result->errorOutput() ?: $result->output(), -800)));

        return false;
    }
}
