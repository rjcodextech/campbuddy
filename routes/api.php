<?php

use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CacheVersionController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\OfferLeadController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\RosterController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public, read-mostly, CDN-cacheable JSON API
|--------------------------------------------------------------------------
| GET routes are public and cacheable at the edge. Mutating routes
| (discovery POST/PATCH/DELETE) are the one place this API writes
| anything, and are throttled here as defense-in-depth on top of the
| CDN-edge limiting — 60/min general, tighter on discovery
| writes specifically.
*/
Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/health', HealthController::class)->name('api.health');
    Route::get('/cache-version', CacheVersionController::class)->name('api.cache-version');

    Route::prefix('events/{event:slug}')->middleware('event.public')->group(function () {
        Route::get('/roster', RosterController::class)->name('api.events.roster');

        Route::get('/discovery', [DiscoveryController::class, 'index'])->name('api.discovery.index');

        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/discovery', [DiscoveryController::class, 'store'])->name('api.discovery.store');
            Route::patch('/discovery/{discoveryId}', [DiscoveryController::class, 'update'])->name('api.discovery.update');
            Route::delete('/discovery/{discoveryId}', [DiscoveryController::class, 'destroy'])->name('api.discovery.destroy');

            Route::post('/offers/{offer}/leads', [OfferLeadController::class, 'store'])->name('api.offers.leads.store');
        });

        Route::post('/push/subscribe', PushSubscriptionController::class)->name('api.push.subscribe');
        Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('api.bookmarks.store');
        Route::delete('/bookmarks', [BookmarkController::class, 'destroy'])->name('api.bookmarks.destroy');
    });
});
