<?php

use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\OfferController;
use App\Http\Controllers\Admin\QuestController;
use App\Http\Controllers\EventPageController;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\Admin\RosterController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RosterRemovalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Attendee-facing routes
|--------------------------------------------------------------------------
| Built out per event across §3 (Home, My Day, Quest, Contribute, Explore,
| Camp Card) — §19 phases 4-11. {event} here binds by slug and is scoped
| to public visibility inside EventPageController — deliberately not a
| global Route::bind(), since the /admin/events/{event} routes reuse the
| same parameter name but must resolve ANY event regardless of status.
*/
Route::get('/', HomeRedirectController::class)->name('home');

Route::middleware('event.public')->group(function () {
    Route::get('/event/{event:slug}', [EventPageController::class, 'home'])->name('event.home');
    Route::get('/event/{event:slug}/my-day', [EventPageController::class, 'myDay'])->name('event.my-day');
    Route::get('/event/{event:slug}/quest', [EventPageController::class, 'quest'])->name('event.quest');
    Route::get('/event/{event:slug}/contribute', [EventPageController::class, 'contribute'])->name('event.contribute');
    Route::get('/event/{event:slug}/explore', [EventPageController::class, 'explore'])->name('event.explore');
    Route::get('/event/{event:slug}/camp-card', [EventPageController::class, 'campCard'])->name('event.camp-card');
    Route::get('/event/{event:slug}/manifest.json', ManifestController::class)->name('event.manifest');

    // §8.4's takedown path — public, no login, throttled against abuse.
    Route::middleware('throttle:20,1')->group(function () {
        Route::get('/event/{event:slug}/roster-removal', [RosterRemovalController::class, 'show'])->name('event.roster-removal.show');
        Route::get('/event/{event:slug}/roster-removal/search', [RosterRemovalController::class, 'search'])->name('event.roster-removal.search');
        Route::post('/event/{event:slug}/roster-removal/{entry}', [RosterRemovalController::class, 'remove'])->name('event.roster-removal.remove');
    });
});

/*
|--------------------------------------------------------------------------
| Admin panel — Breeze-scaffolded, session-based auth (§6, §9)
|--------------------------------------------------------------------------
| Kept under /admin so it never bleeds into the attendee-facing app or its
| design system (§4.5). Route *names* stay Breeze's defaults (login,
| dashboard, profile.*, ...) so the framework's own redirect targets
| (e.g. the Authenticate middleware's route('login')) resolve correctly
| without extra config — only the URI prefix changes.
*/
Route::prefix('admin')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', function () {
            return view('dashboard');
        })->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Full CRUD on events (§9) — Create/Read/Update always available;
        // Delete is policy-gated to draft events only (EventPolicy::delete).
        Route::resource('events', EventController::class)->except('show')->names('admin.events');
        Route::post('events/{event}/refresh', [EventController::class, 'refresh'])->name('admin.events.refresh');
        Route::post('events/{event}/refresh-branding', [EventController::class, 'refreshBranding'])->name('admin.events.refresh-branding');
        Route::post('events/{event}/branding', [EventController::class, 'uploadBranding'])->name('admin.events.upload-branding');
        Route::put('events/{event}/info', [EventController::class, 'updateInfo'])->name('admin.events.update-info');

        Route::get('events/{event}/quests', [QuestController::class, 'index'])->name('admin.events.quests.index');
        Route::post('events/{event}/quests', [QuestController::class, 'store'])->name('admin.events.quests.store');
        Route::put('events/{event}/quests/{quest}', [QuestController::class, 'update'])->name('admin.events.quests.update');
        Route::delete('events/{event}/quests/{quest}', [QuestController::class, 'destroy'])->name('admin.events.quests.destroy');

        Route::get('events/{event}/offers', [OfferController::class, 'index'])->name('admin.events.offers.index');
        Route::post('events/{event}/offers', [OfferController::class, 'store'])->name('admin.events.offers.store');
        Route::put('events/{event}/offers/{offer}', [OfferController::class, 'update'])->name('admin.events.offers.update');
        Route::delete('events/{event}/offers/{offer}', [OfferController::class, 'destroy'])->name('admin.events.offers.destroy');

        Route::get('events/{event}/roster', [RosterController::class, 'index'])->name('admin.events.roster.index');
        Route::post('events/{event}/roster/{entry}/suppress', [RosterController::class, 'suppress'])->name('admin.events.roster.suppress');
        Route::post('events/{event}/roster/{entry}/unsuppress', [RosterController::class, 'unsuppress'])->name('admin.events.roster.unsuppress');
    });

    require __DIR__.'/auth.php';
});
