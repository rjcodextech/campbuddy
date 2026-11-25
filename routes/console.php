<?php

use App\Jobs\DiscoverWordCampsJob;
use App\Jobs\EvaluateEventLifecycleJob;
use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Jobs\FetchContributorTablesJob;
use App\Jobs\FillMissingPhotosJob;
use App\Jobs\ParseAttendeeRosterJob;
use App\Jobs\SendSessionRemindersJob;
use App\Jobs\SendAfterEventPushesJob;
use App\Jobs\SendThankYouPushJob;
use App\Models\DeviceTransfer;
use App\Models\Event;
use App\Support\EventTime;
use App\Support\FetchLogRetention;
use App\Support\SystemHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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
| multiple upstream WordCamp sites at once. A finished event stops being
| fetched RETENTION_DAYS after its last day (EventTime::refreshing), though
| it stays listed as "Completed" for three months.
*/
Schedule::call(function () {
    Event::whereIn('status', ['approved', 'active'])->get()->filter(fn (Event $event) => EventTime::refreshing($event))->values()->each(function (Event $event, int $index) {
        FetchSpeakersSponsorsSessionsJob::dispatch($event)->delay(now()->addSeconds($index * 5));
    });
})->twiceDaily(2, 14)->name('ingest-sessions-speakers-sponsors')->withoutOverlapping(30);

// On the event day(s), event-related ingestion runs on every hour for live schedule & roster accuracy.
Schedule::call(function () {
    Event::where('status', 'active')
        ->get()
        ->filter(fn (Event $event) => EventTime::isEventDay($event))
        ->values()
        ->each(function (Event $event, int $index) {
            FetchSpeakersSponsorsSessionsJob::dispatch($event)->delay(now()->addSeconds($index * 5));
            FetchEventInfoJob::dispatch($event)->delay(now()->addSeconds($index * 10));
            if ($event->attendeeRoster()->exists()) {
                ParseAttendeeRosterJob::dispatch($event)->delay(now()->addSeconds($index * 15));
            }
        });
})->hourly()->name('event-day-hourly-refresh')->withoutOverlapping(30);

// Event Information (venue, links, contact…) runs twice daily for approved and live events. Admin-typed values are never touched.
Schedule::call(function () {
    Event::whereIn('status', ['approved', 'active'])->get()->filter(fn (Event $event) => EventTime::refreshing($event))->values()->each(function (Event $event, int $index) {
        FetchEventInfoJob::dispatch($event)->delay(now()->addSeconds($index * 10));
    });
})->cron('10 2,14 * * *')->name('ingest-event-info')->withoutOverlapping(30);

// Twice daily staggered per active event (00:00 and 12:00); upserts new attendees and keeps suppressed entries suppressed.
Schedule::call(function () {
    Event::where('status', 'active')->get()->filter(fn (Event $event) => EventTime::refreshing($event))->values()->each(function (Event $event, int $index) {
        ParseAttendeeRosterJob::dispatch($event)->delay(now()->addSeconds($index * 10));
    });
})->twiceDaily(0, 12)->name('ingest-attendee-roster')->withoutOverlapping(30);

// A real photo for everyone on the attendee list (FillMissingPhotosJob), after each roster scrape.
Schedule::call(function () {
    Event::where('status', 'active')->get()->filter(fn (Event $event) => EventTime::refreshing($event))->values()->each(function (Event $event, int $index) {
        FillMissingPhotosJob::dispatch($event, retryMissing: true)->delay(now()->addSeconds($index * 20));
    });
})->cron('40 0,12 * * *')->name('fill-missing-photos')->withoutOverlapping(30);

// Contributor Day teams and table leads from the site's own pages (FetchContributorTablesJob), twice daily.
Schedule::call(function () {
    Event::whereIn('status', ['approved', 'active'])->get()->filter(fn (Event $event) => EventTime::refreshing($event))->values()->each(function (Event $event, int $index) {
        FetchContributorTablesJob::dispatch($event)->delay(now()->addSeconds($index * 10));
    });
})->cron('15 2,14 * * *')->name('fetch-contributor-tables')->withoutOverlapping(30);

Schedule::call(function () {
    Event::whereIn('status', ['approved', 'active'])
        ->where(fn ($q) => $q->whereNull('logo_path')->orWhereNull('favicon_path'))
        ->get()->filter(fn (Event $event) => EventTime::refreshing($event))->values()
        ->each(function (Event $event, int $index) {
            FetchBrandingAssetsJob::dispatch($event, onlyMissing: true)->delay(now()->addSeconds($index * 10));
        });
})->cron('30 2,14 * * *')->name('backfill-event-branding')->withoutOverlapping(30);

// Proof of life for the admin dashboard: if this stops being written, the
// cron entry that runs the scheduler isn't running (App\Support\SystemHealth).
Schedule::call(fn () => Cache::forever(SystemHealth::HEARTBEAT_KEY, now()->toIso8601String()))
    ->everyMinute()
    ->name('heartbeat');

// The reminder window is 5-10 minutes before a session — every
// minute is the tightest useful cadence without spamming the queue.
Schedule::job(new SendSessionRemindersJob)->everyMinute()->name('send-session-reminders')->withoutOverlapping(5);

// Day 3 after a WordCamp: one thank-you push per event (rating + PDF export).
// Hourly, so each venue gets it in its own daytime (the job checks the hour).
Schedule::job(new SendThankYouPushJob)->hourlyAt(5)->name('send-thank-you-push')->withoutOverlapping(10);

// Day 7 ("save your data") and day 15 ("take it with you") after a WordCamp:
// once each per event, in the venue's daytime (the job checks the hour).
Schedule::job(new SendAfterEventPushesJob)->hourlyAt(10)->name('send-after-event-pushes')->withoutOverlapping(10);

// Central discovery, every 2 days — new WordCamps don't appear hourly,
// and every result lands as a draft pending admin approval. Laravel's
// scheduler has no built-in "every N days" helper, so this is a raw cron
// expression: day-of-month divisible by 2 (i.e. every even calendar
// day), not a rolling 48-hour timer — close enough for a discovery job
// with no real time pressure.
Schedule::job(new DiscoverWordCampsJob)->twiceDaily(3, 15)->name('discover-wordcamps')->withoutOverlapping(30);

// Auto-publish a draft once its site is up and it has a real attendee,
// and archive whatever's dates have already passed — twice daily.
Schedule::job(new EvaluateEventLifecycleJob)->twiceDaily(1, 13)->name('evaluate-event-lifecycle')->withoutOverlapping(30);

// Keeps 7 days plus the newest row of each (event, kind of fetch); runs twice daily.
Schedule::call(fn () => FetchLogRetention::prune())
    ->cron('20 3,15 * * *')
    ->name('prune-fetch-log')
    ->withoutOverlapping(30);
// Automatically cleans unused social media artwork caches and old expired event caches twice daily.
Schedule::call(fn () => Artisan::call('campbuddy:clear-social-cache'))
    ->cron('15 0,12 * * *')
    ->name('clear-social-cache')
    ->withoutOverlapping(30);
// The posts due today (SocialPlan), pushed to organizers; hourly at :15.
Schedule::call(fn () => Artisan::call('campbuddy:post-reminders'))
    ->hourlyAt(15)
    ->name('post-reminders')
    ->withoutOverlapping(10);
// Notify search engines (IndexNow) twice daily in production.
Schedule::call(fn () => Artisan::call('campbuddy:indexnow'))
    ->cron('30 4,16 * * *')
    ->name('indexnow')
    ->withoutOverlapping(30);

// DPDPA compliance: erase expired shared personal data twice daily.
Schedule::call(fn () => Artisan::call('campbuddy:erase-expired-data'))
    ->cron('40 3,15 * * *')
    ->name('erase-expired-personal-data')
    ->withoutOverlapping(30);

// "Move my CampBuddy to this device" requests (DeviceTransfer) live minutes at
// most; this cleans up expired ones every 15 minutes instead of hammering the DB every 5.
Schedule::call(fn () => DeviceTransfer::prune())
    ->everyFifteenMinutes()
    ->name('prune-device-transfers')
    ->withoutOverlapping(10);

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
