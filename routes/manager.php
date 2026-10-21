<?php

use App\Http\Controllers\Manager\AttendeeController;
use App\Http\Controllers\Manager\AuthController;
use App\Http\Controllers\Manager\ContributorTableController;
use App\Http\Controllers\Manager\DashboardController;
use App\Http\Controllers\Manager\EventController;
use App\Http\Controllers\Manager\MemoriesController;
use App\Http\Controllers\AppPushController;
use App\Http\Controllers\Manager\NotificationsController;
use App\Http\Controllers\Manager\ReportsController;
use App\Http\Controllers\Manager\QuestController;
use App\Http\Controllers\Manager\ScheduleDelayController;
use App\Http\Controllers\Manager\SocialController;
use App\Http\Middleware\EnsureEventManager;
use App\Http\Middleware\ThrottleManagerWrites;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Event manager area — /manager
|--------------------------------------------------------------------------
| People an admin has given a few events to look after (created under
| Admin → Event managers; nobody registers). They sign in here, on their own
| page and their own guard, and can edit three sections of *their* events:
| details, event information, quests & checklist — never an event's status.
|
| Deliberately no {event} model binding: every controller looks the event up
| through the signed-in manager's own events, so someone else's event — or a
| quest of another event — is a plain 404 whatever ids are typed in.
*/
Route::prefix('manager')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('manager.login');
    // The real brake on guessing is the per-email + address limiter inside ManagerLoginRequest (5 wrong tries).
    // This one only stops floods; it is per address, and a venue's wifi puts a dozen organizers behind one.
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:30,1')->name('manager.login.store');

    Route::middleware([EnsureEventManager::class, ThrottleManagerWrites::class])->whereNumber(['eventId', 'questId'])->group(function () {
        Route::post('logout', [AuthController::class, 'destroy'])->name('manager.logout');

        Route::get('/', DashboardController::class)->name('manager.dashboard');

        Route::get('events/{eventId}', [EventController::class, 'details'])->name('manager.events.details');
        Route::put('events/{eventId}', [EventController::class, 'updateDetails'])->name('manager.events.details.update');

        Route::get('events/{eventId}/information', [EventController::class, 'information'])->name('manager.events.information');
        Route::put('events/{eventId}/information', [EventController::class, 'updateInformation'])->name('manager.events.information.update');

        Route::get('events/{eventId}/quests', [QuestController::class, 'index'])->name('manager.events.quests');
        Route::get('events/{eventId}/qr', [SocialController::class, 'qr'])->name('manager.events.qr');
        Route::get('events/{eventId}/social', [SocialController::class, 'index'])->name('manager.events.social');
        Route::post('events/{eventId}/social/colors', [SocialController::class, 'colors'])->name('manager.events.social.colors');
        Route::get('events/{eventId}/social/image', [SocialController::class, 'image'])->name('manager.events.social.image');
        Route::post('events/{eventId}/social/site', [SocialController::class, 'site'])->name('manager.events.social.site');
        Route::post('events/{eventId}/social/art', [SocialController::class, 'art'])->middleware('throttle:20,1')->name('manager.events.social.art');
        Route::post('events/{eventId}/social/art/remove', [SocialController::class, 'removeArt'])->name('manager.events.social.art.remove');
        Route::post('events/{eventId}/social/art/use', [SocialController::class, 'useArt'])->name('manager.events.social.art.use');
        Route::post('events/{eventId}/social/art/kind', [SocialController::class, 'artKind'])->name('manager.events.social.art.kind');
        Route::post('events/{eventId}/social/webhook', [SocialController::class, 'webhook'])->name('manager.events.social.webhook');
        Route::post('events/{eventId}/social/publish', [SocialController::class, 'publish'])->middleware('throttle:20,1')->name('manager.events.social.publish');
        Route::get('events/{eventId}/attendees', [AttendeeController::class, 'index'])->name('manager.events.attendees');
        Route::get('events/{eventId}/delay', [ScheduleDelayController::class, 'index'])->name('manager.events.delay');
        Route::post('events/{eventId}/delay', [ScheduleDelayController::class, 'store'])->name('manager.events.delay.store');
        Route::post('events/{eventId}/delay/clear', [ScheduleDelayController::class, 'clear'])->name('manager.events.delay.clear');
        Route::get('events/{eventId}/memories', [MemoriesController::class, 'index'])->name('manager.events.memories');
        Route::post('events/{eventId}/broadcasts', [\App\Http\Controllers\Manager\BroadcastController::class, 'store'])->middleware('throttle:10,1')->name('manager.events.broadcasts.store');
        Route::get('events/{eventId}/notifications', NotificationsController::class)->name('manager.events.notifications');
        Route::get('events/{eventId}/reports', [ReportsController::class, 'index'])->name('manager.events.reports');
        Route::post('events/{eventId}/reports/{discoveryId}/remove', [ReportsController::class, 'remove'])->where('discoveryId', '[a-f0-9]{64}')->name('manager.events.reports.remove');
        Route::post('events/{eventId}/reports/{discoveryId}/dismiss', [ReportsController::class, 'dismiss'])->where('discoveryId', '[a-f0-9]{64}')->name('manager.events.reports.dismiss');
        // The store app: this organizer's phone for alerts (reports on a profile).
        Route::post('app-push', [AppPushController::class, 'store'])->name('manager.app-push.store');
        Route::delete('app-push', [AppPushController::class, 'destroy'])->name('manager.app-push.destroy');
        Route::post('events/{eventId}/memories', [MemoriesController::class, 'store'])->name('manager.events.memories.store');
        Route::post('events/{eventId}/memories/notify/{kind}', [MemoriesController::class, 'notify'])->whereIn('kind', ['photos', 'videos'])->middleware('throttle:10,1')->name('manager.events.memories.notify');
        Route::get('events/{eventId}/tables', [ContributorTableController::class, 'index'])->name('manager.events.tables');
        Route::post('events/{eventId}/tables', [ContributorTableController::class, 'store'])->name('manager.events.tables.store');
        Route::put('events/{eventId}/tables/{tableId}', [ContributorTableController::class, 'update'])->whereNumber('tableId')->name('manager.events.tables.update');
        Route::delete('events/{eventId}/tables/{tableId}', [ContributorTableController::class, 'destroy'])->whereNumber('tableId')->name('manager.events.tables.destroy');
        Route::post('events/{eventId}/attendees/{entryId}/roles', [AttendeeController::class, 'roles'])->whereNumber('entryId')->name('manager.events.attendees.roles');
        Route::post('events/{eventId}/quests', [QuestController::class, 'store'])->name('manager.events.quests.store');
        Route::put('events/{eventId}/quests/{questId}', [QuestController::class, 'update'])->name('manager.events.quests.update');
        Route::delete('events/{eventId}/quests/{questId}', [QuestController::class, 'destroy'])->name('manager.events.quests.destroy');
    });
});
