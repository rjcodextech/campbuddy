<?php

use App\Jobs\DiscoverWordCampsJob;
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

// Central discovery — weekly is plenty; new WordCamps don't appear
// hourly, and every result lands as a draft pending admin approval.
Schedule::job(new DiscoverWordCampsJob)->weekly()->name('discover-wordcamps');
