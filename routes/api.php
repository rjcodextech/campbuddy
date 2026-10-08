<?php

use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CacheVersionController;
use App\Http\Controllers\Api\DataVersionController;
use App\Http\Controllers\Api\DeviceTransferController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\DiscoveryWaveController;
use App\Http\Controllers\Api\EventFeedbackController;
use App\Http\Controllers\Api\FreeStealSuggestionController;
use App\Http\Controllers\Api\OfferLeadController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\RosterController;
use App\Http\Controllers\Api\SharedCampCardController;
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
Route::prefix('v1')->middleware('throttle:api-general')->group(function () {
    Route::get('/health', HealthController::class)->name('api.health');
    Route::get('/cache-version', CacheVersionController::class)->name('api.cache-version');

    Route::prefix('events/{event:slug}')->middleware('event.public')->group(function () {
        Route::get('/roster', RosterController::class)->name('api.events.roster');
        Route::get('/data-version', DataVersionController::class)->name('api.events.data-version');

        Route::get('/discovery', [DiscoveryController::class, 'index'])->name('api.discovery.index');
        // Waves: only the profile's owner (bearer owner token) sees its own.
        Route::get('/discovery/{discoveryId}/waves', [DiscoveryWaveController::class, 'index'])->name('api.discovery.waves.index');

        // Moving an attendee's data to another device (DeviceTransferController):
        // each call is authorized by a secret only one of the two devices holds.
        Route::get('/discovery/{discoveryId}/transfer', [DeviceTransferController::class, 'pending'])->name('api.device-transfers.pending');
        Route::get('/device-transfers/{transferId}', [DeviceTransferController::class, 'show'])->name('api.device-transfers.show');
        Route::get('/device-transfers/{transferId}/sender', [DeviceTransferController::class, 'senderStatus'])->name('api.device-transfers.sender');

        Route::middleware('throttle:api-writes')->group(function () {
            Route::post('/push/subscribe', PushSubscriptionController::class)->name('api.push.subscribe');
            Route::post('/discovery', [DiscoveryController::class, 'store'])->name('api.discovery.store');
            Route::patch('/discovery/{discoveryId}', [DiscoveryController::class, 'update'])->name('api.discovery.update');
            Route::delete('/discovery/{discoveryId}', [DiscoveryController::class, 'destroy'])->name('api.discovery.destroy');
            Route::post('/discovery/{discoveryId}/waves', [DiscoveryWaveController::class, 'store'])->name('api.discovery.waves.store');
            Route::post('/discovery/{discoveryId}/messages', [DiscoveryWaveController::class, 'message'])->name('api.discovery.messages.store');
            Route::delete('/discovery/{discoveryId}/waves/{targetId}', [DiscoveryWaveController::class, 'destroy'])->name('api.discovery.waves.destroy');

            Route::post('/device-transfers', [DeviceTransferController::class, 'store'])->name('api.device-transfers.store');
            Route::post('/device-transfers/{transferId}/complete', [DeviceTransferController::class, 'complete'])->name('api.device-transfers.complete');
            Route::delete('/device-transfers/{transferId}', [DeviceTransferController::class, 'destroy'])->name('api.device-transfers.destroy');
            Route::post('/discovery/{discoveryId}/transfer/{transferId}/approve', [DeviceTransferController::class, 'approve'])->name('api.device-transfers.approve');
            Route::post('/discovery/{discoveryId}/transfer/{transferId}/decline', [DeviceTransferController::class, 'decline'])->name('api.device-transfers.decline');

            Route::post('/offers/{offer}/leads', [OfferLeadController::class, 'store'])->name('api.offers.leads.store');
            Route::post('/free-steal-suggestions', [FreeStealSuggestionController::class, 'store'])->name('api.free-steal-suggestions.store');
            // The after-event thank-you card's rating (one per device, changeable).
            Route::post('/feedback', [EventFeedbackController::class, 'store'])->name('api.feedback.store');

            Route::post('/camp-card-share', [SharedCampCardController::class, 'store'])->name('api.camp-card-share.store');
            Route::put('/camp-card-share/{shareId}', [SharedCampCardController::class, 'update'])->name('api.camp-card-share.update');
            Route::delete('/camp-card-share/{shareId}', [SharedCampCardController::class, 'destroy'])->name('api.camp-card-share.destroy');
        });

        // Reminder bookmarks: a public write, so throttled too — on their own,
        // roomier limiter, since saving a morning's sessions is a quick burst.
        Route::middleware('throttle:api-bookmarks')->group(function () {
            Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('api.bookmarks.store');
            Route::delete('/bookmarks', [BookmarkController::class, 'destroy'])->name('api.bookmarks.destroy');
        });
    });
});
