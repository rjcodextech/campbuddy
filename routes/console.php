<?php

use App\Jobs\DiscoverWordCampsJob;
use App\Jobs\EvaluateEventLifecycleJob;
use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Jobs\ParseAttendeeRosterJob;
use App\Jobs\SendSessionRemindersJob;
use App\Models\Event;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled ingestion
|--------------------------------------------------------------------------
| One cPanel cron entry (`schedule:run` every minute) dispatches
| each job on its own cadence from here — and, last of all, works the queue
| off (see "Queue worker" at the bottom), so no separate worker process is
| needed on shared hosting. FetchSpeakersSponsorsSessionsJob
| runs every 15 minutes per active event, matching V1's old refresh
| cadence; ParseAttendeeRosterJob runs once daily at midnight since
| it's a scrape of someone else's site, not an API with a rate limit.
| FetchBrandingAssetsJob runs when an event goes live (EventObserver) and,
| as a safety net, a daily backfill for live events still missing a logo or
| favicon.
|
| Jobs run sequentially with a small stagger, never concurrently against
| multiple upstream WordCamp sites at once.
*/
Schedule::call(function () {
    Event::where('status', 'active')->each(function (Event $event, int $index) {
        FetchSpeakersSponsorsSessionsJob::dispatch($event)->delay(now()->addSeconds($index * 5));
    });
})->everyFifteenMinutes()->name('ingest-sessions-speakers-sponsors');

// Event Information (venue, links, contact…) changes rarely — venue and
// pages get filled in as organizers finish them — so once a day is plenty,
// for events that are approved or live. Admin-typed values are never touched.
Schedule::call(function () {
    Event::whereIn('status', ['approved', 'active'])->each(function (Event $event, int $index) {
        FetchEventInfoJob::dispatch($event)->delay(now()->addSeconds($index * 10));
    });
})->dailyAt('02:00')->name('ingest-event-info');

// Midnight in the app's timezone (APP_TIMEZONE, UTC unless set), every active
// event, staggered so two WordCamp sites are never scraped at once. The job
// upserts new attendees and drops the ones who've left the source page.
Schedule::call(function () {
    Event::where('status', 'active')->each(function (Event $event, int $index) {
        ParseAttendeeRosterJob::dispatch($event)->delay(now()->addSeconds($index * 10));
    });
})->dailyAt('00:00')->name('ingest-attendee-roster');

// Branding is fetched when an event goes live, but that one-shot can miss
// (site down at that moment, queue hiccup). Retry daily for any live event
// still without a logo or favicon — only the missing one, never overwriting
// what an admin uploaded.
Schedule::call(function () {
    Event::whereIn('status', ['approved', 'active'])
        ->where(fn ($q) => $q->whereNull('logo_path')->orWhereNull('favicon_path'))
        ->each(function (Event $event, int $index) {
            FetchBrandingAssetsJob::dispatch($event, onlyMissing: true)->delay(now()->addSeconds($index * 10));
        });
})->dailyAt('02:30')->name('backfill-event-branding');

// The reminder window is 5-10 minutes before a session — every
// minute is the tightest useful cadence without spamming the queue.
Schedule::job(new SendSessionRemindersJob)->everyMinute()->name('send-session-reminders');

// Central discovery, every 2 days — new WordCamps don't appear hourly,
// and every result lands as a draft pending admin approval. Laravel's
// scheduler has no built-in "every N days" helper, so this is a raw cron
// expression: day-of-month divisible by 2 (i.e. every even calendar
// day), not a rolling 48-hour timer — close enough for a discovery job
// with no real time pressure.
Schedule::job(new DiscoverWordCampsJob)->cron('0 3 */2 * *')->name('discover-wordcamps');

// Auto-publish a draft once its site is up and it has a real attendee,
// and archive whatever's dates have already passed — daily, offset from
// midnight so it doesn't pile onto ingest-attendee-roster's own run.
Schedule::job(new EvaluateEventLifecycleJob)->dailyAt('01:00')->name('evaluate-event-lifecycle');

/*
|--------------------------------------------------------------------------
| Queue worker
|--------------------------------------------------------------------------
| Everything above only *dispatches* onto the queue (QUEUE_CONNECTION=
| database). Shared/cPanel hosting has no long-running worker, so without
| this nothing would ever run: no event branding, no schedule refreshes, no
| reminders. Once a minute, after the dispatchers above have queued their
| work, drain the queue and stop. It runs in this same process (Artisan::call,
| not a spawned command — hosts often disable proc_open) and is registered
| last so it sees everything queued this minute. If a real worker (Supervisor
| etc.) is already running, this is harmless — jobs are reserved, never run
| twice — and it can be dropped.
*/
Schedule::call(function () {
    Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--max-time' => 50,
        '--tries' => 3,
    ]);
})->everyMinute()
    ->name('work-queue')
    ->withoutOverlapping(10)
    ->when(fn () => config('queue.default') !== 'sync');
