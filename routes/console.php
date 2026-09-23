<?php

use App\Jobs\DiscoverWordCampsJob;
use App\Jobs\EvaluateEventLifecycleJob;
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
| each job on its own cadence from here. FetchSpeakersSponsorsSessionsJob
| runs every 15 minutes per active event, matching V1's old refresh
| cadence; ParseAttendeeRosterJob runs once daily since
| it's a scrape of someone else's site, not an API with a rate limit.
| FetchBrandingAssetsJob is on-demand only, not on this schedule.
|
| Jobs run sequentially with a small stagger, never concurrently against
| multiple upstream WordCamp sites at once.
*/
Schedule::call(function () {
    Event::where('status', 'active')->each(function (Event $event, int $index) {
        FetchSpeakersSponsorsSessionsJob::dispatch($event)->delay(now()->addSeconds($index * 5));
    });
})->everyFifteenMinutes()->name('ingest-sessions-speakers-sponsors');

Schedule::call(function () {
    Event::where('status', 'active')->each(function (Event $event, int $index) {
        ParseAttendeeRosterJob::dispatch($event)->delay(now()->addSeconds($index * 10));
    });
})->daily()->name('ingest-attendee-roster');

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
