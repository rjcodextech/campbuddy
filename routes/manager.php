<?php

use App\Http\Controllers\Manager\AuthController;
use App\Http\Controllers\Manager\DashboardController;
use App\Http\Controllers\Manager\EventController;
use App\Http\Controllers\Manager\QuestController;
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
        Route::post('events/{eventId}/quests', [QuestController::class, 'store'])->name('manager.events.quests.store');
        Route::put('events/{eventId}/quests/{questId}', [QuestController::class, 'update'])->name('manager.events.quests.update');
        Route::delete('events/{eventId}/quests/{questId}', [QuestController::class, 'destroy'])->name('manager.events.quests.destroy');
    });
});
