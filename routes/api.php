<?php

use App\Http\Controllers\Api\App\ConfigController as AppSettingsController;
use App\Http\Controllers\Api\App\EventController as AppEventController;
use App\Http\Controllers\Api\App\OrganizerAdminController as AppOrganizerAdminController;
use App\Http\Controllers\Api\App\OrganizerController as AppOrganizerController;
use App\Http\Controllers\Api\App\OrganizerManageController as AppOrganizerManageController;
use App\Http\Controllers\Api\App\OrganizerSocialController as AppOrganizerSocialController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CacheVersionController;
use App\Http\Controllers\Api\DataVersionController;
use App\Http\Controllers\Api\DeviceTransferController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\DiscoveryReportController;
use App\Http\Controllers\Api\DiscoveryWaveController;
use App\Http\Controllers\Api\EventFeedbackController;
use App\Http\Controllers\Api\FreeStealSuggestionController;
use App\Http\Controllers\Api\LegalApiController;
use App\Http\Controllers\Api\NativePushTokenController;
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
    // The native app start-up check: minimum build, store link, feature switches.
    Route::get('/app-config', AppConfigController::class)->name('api.app-config');
    // The native app's server-driven settings (Admin > Mobile app).
    Route::get('/app/config', AppSettingsController::class)->name('api.app.config');
    // Android APK updates (Admin > Mobile app > App updates): is a newer build live, and its file.
    Route::get('/app/update', [\App\Http\Controllers\Api\AppUpdateController::class, 'show'])->name('api.app.update');
    Route::get('/app/releases/{package}/{code}.apk', [\App\Http\Controllers\Api\AppUpdateController::class, 'download'])
        ->where(['package' => '[a-z][a-z0-9_]*(\.[a-z0-9_]+)+', 'code' => '[0-9]{1,10}'])->name('api.app.releases.download');

    // Legal documents for direct in-app native rendering (Privacy notice & Terms of use)
    Route::get('/legal/{slug}', [LegalApiController::class, 'show'])->whereIn('slug', ['privacy', 'terms'])->name('api.legal.show');

    // The native app organizer endpoints (admins and event managers, bearer token).
    Route::post('/app/auth/login', [AppOrganizerController::class, 'login'])->middleware('throttle:20,1')->name('api.app.auth.login');
    Route::middleware(\App\Http\Middleware\AppOrganizerAuth::class)->prefix('app')->group(function () {
        Route::post('/auth/logout', [AppOrganizerController::class, 'logout'])->name('api.app.auth.logout');
        Route::get('/organizer/me', [AppOrganizerController::class, 'me'])->name('api.app.organizer.me');
        Route::get('/organizer/events', [AppOrganizerController::class, 'events'])->name('api.app.organizer.events');
        Route::prefix('/organizer/events/{id}')->whereNumber('id')->group(function () {
            Route::get('/overview', [AppOrganizerController::class, 'overview'])->name('api.app.organizer.overview');
            Route::post('/info', [AppOrganizerController::class, 'updateInfo'])->name('api.app.organizer.info.update');
            Route::get('/reports', [AppOrganizerController::class, 'reports'])->name('api.app.organizer.reports');
            Route::post('/reports/{discoveryId}/remove', [AppOrganizerController::class, 'removeReported'])->where('discoveryId', '[a-f0-9]{64}')->name('api.app.organizer.reports.remove');
            Route::post('/reports/{discoveryId}/dismiss', [AppOrganizerController::class, 'dismissReported'])->where('discoveryId', '[a-f0-9]{64}')->name('api.app.organizer.reports.dismiss');
            Route::get('/delay', [AppOrganizerController::class, 'delay'])->name('api.app.organizer.delay');
            Route::post('/delay', [AppOrganizerController::class, 'setDelay'])->name('api.app.organizer.delay.set');
            Route::post('/delay/clear', [AppOrganizerController::class, 'clearDelay'])->name('api.app.organizer.delay.clear');
            Route::post('/memories', [AppOrganizerController::class, 'updateMemories'])->name('api.app.organizer.memories.update');
            Route::post('/memories/notify/{kind}', [AppOrganizerController::class, 'notifyMemories'])->whereIn('kind', ['photos', 'videos'])->name('api.app.organizer.memories.notify');
            Route::get('/attendees', [AppOrganizerController::class, 'attendees'])->name('api.app.organizer.attendees');
            Route::post('/attendees/{entryId}/roles', [AppOrganizerController::class, 'setAttendeeRoles'])->whereNumber('entryId')->name('api.app.organizer.attendees.roles');

            // The rest of the web panel's event tabs (OrganizerManageController): admins and the event's managers.
            Route::get('/details', [AppOrganizerManageController::class, 'details'])->name('api.app.organizer.details');
            Route::get('/information', [AppOrganizerManageController::class, 'information'])->name('api.app.organizer.information');
            Route::get('/quests', [AppOrganizerManageController::class, 'quests'])->name('api.app.organizer.quests');
            Route::get('/tables', [AppOrganizerManageController::class, 'tables'])->name('api.app.organizer.tables');
            Route::get('/notifications', [AppOrganizerManageController::class, 'notifications'])->name('api.app.organizer.notifications');
            Route::get('/broadcasts', [AppOrganizerManageController::class, 'broadcasts'])->name('api.app.organizer.broadcasts');
            Route::post('/broadcasts', [AppOrganizerManageController::class, 'sendBroadcast'])->middleware('throttle:10,1')->name('api.app.organizer.broadcasts.send');
            // Admin only (the controller answers 403 to a manager).
            Route::get('/activity', [AppOrganizerManageController::class, 'activity'])->name('api.app.organizer.activity');
            Route::get('/deals', [AppOrganizerManageController::class, 'deals'])->name('api.app.organizer.deals');
            Route::get('/leads', [AppOrganizerManageController::class, 'leads'])->name('api.app.organizer.leads');
            Route::get('/leads/export', [AppOrganizerManageController::class, 'exportLeads'])->middleware('throttle:20,1')->name('api.app.organizer.leads.export');

            Route::middleware('throttle:60,1')->group(function () {
                Route::post('/details', [AppOrganizerManageController::class, 'updateDetails'])->name('api.app.organizer.details.update');
                Route::post('/information', [AppOrganizerManageController::class, 'updateInformation'])->name('api.app.organizer.information.update');
                Route::post('/quests', [AppOrganizerManageController::class, 'storeQuest'])->name('api.app.organizer.quests.store');
                Route::patch('/quests/{itemId}', [AppOrganizerManageController::class, 'updateQuest'])->whereNumber('itemId')->name('api.app.organizer.quests.update');
                Route::delete('/quests/{itemId}', [AppOrganizerManageController::class, 'destroyQuest'])->whereNumber('itemId')->name('api.app.organizer.quests.destroy');
                Route::post('/tables', [AppOrganizerManageController::class, 'storeTable'])->name('api.app.organizer.tables.store');
                Route::patch('/tables/{itemId}', [AppOrganizerManageController::class, 'updateTable'])->whereNumber('itemId')->name('api.app.organizer.tables.update');
                Route::delete('/tables/{itemId}', [AppOrganizerManageController::class, 'destroyTable'])->whereNumber('itemId')->name('api.app.organizer.tables.destroy');
                // Admin only.
                Route::post('/attendees/{entryId}/{action}', [AppOrganizerManageController::class, 'attendeeAction'])->whereNumber('entryId')->whereIn('action', ['suppress', 'unsuppress', 'release-claim', 'remove-card'])->name('api.app.organizer.attendees.action');
                Route::post('/fetch/{job}', [AppOrganizerManageController::class, 'fetch'])->whereIn('job', ['schedule', 'info', 'branding'])->middleware('throttle:10,1')->name('api.app.organizer.fetch');
                Route::post('/deals', [AppOrganizerManageController::class, 'storeDeal'])->name('api.app.organizer.deals.store');
                Route::patch('/deals/{itemId}', [AppOrganizerManageController::class, 'updateDeal'])->whereNumber('itemId')->name('api.app.organizer.deals.update');
                Route::delete('/deals/{itemId}', [AppOrganizerManageController::class, 'destroyDeal'])->whereNumber('itemId')->name('api.app.organizer.deals.destroy');
                Route::post('/deals/{itemId}/visibility', [AppOrganizerManageController::class, 'dealVisibility'])->whereNumber('itemId')->name('api.app.organizer.deals.visibility');
                Route::delete('/', [AppOrganizerManageController::class, 'destroy'])->name('api.app.organizer.events.destroy');

                // Social media (OrganizerSocialController): admins and the event's managers.
                Route::post('/social/colors', [AppOrganizerSocialController::class, 'colors'])->name('api.app.organizer.social.colors');
                Route::post('/social/site', [AppOrganizerSocialController::class, 'site'])->middleware('throttle:10,1')->name('api.app.organizer.social.site');
                Route::post('/social/art/{action}', [AppOrganizerSocialController::class, 'art'])->whereIn('action', ['use', 'remove', 'kind'])->name('api.app.organizer.social.art');
                Route::post('/social/webhook', [AppOrganizerSocialController::class, 'webhook'])->name('api.app.organizer.social.webhook');
                Route::post('/social/publish', [AppOrganizerSocialController::class, 'publish'])->middleware('throttle:20,1')->name('api.app.organizer.social.publish');
            });
            Route::get('/social', [AppOrganizerSocialController::class, 'show'])->name('api.app.organizer.social');
        });
    });

    // The admin's platform pages in the app (OrganizerAdminController): admins only.
    Route::middleware(\App\Http\Middleware\AppOrganizerAuth::class.':admin')->prefix('app/organizer/admin')->group(function () {
        Route::get('/dashboard', [AppOrganizerAdminController::class, 'dashboard'])->name('api.app.admin.dashboard');
        Route::get('/errors', [AppOrganizerAdminController::class, 'errors'])->name('api.app.admin.errors');
        Route::get('/feedback', [AppOrganizerAdminController::class, 'feedback'])->name('api.app.admin.feedback');
        Route::get('/managers', [AppOrganizerAdminController::class, 'managers'])->name('api.app.admin.managers');
        Route::get('/activity', [AppOrganizerAdminController::class, 'activity'])->name('api.app.admin.activity');
        Route::get('/event-options', [AppOrganizerAdminController::class, 'eventOptions'])->name('api.app.admin.event-options');
        Route::get('/analytics', [AppOrganizerAdminController::class, 'analytics'])->middleware('throttle:30,1')->name('api.app.admin.analytics');

        Route::middleware('throttle:60,1')->group(function () {
            Route::post('/managers', [AppOrganizerAdminController::class, 'storeManager'])->name('api.app.admin.managers.store');
            Route::patch('/managers/{itemId}', [AppOrganizerAdminController::class, 'updateManager'])->whereNumber('itemId')->name('api.app.admin.managers.update');
            Route::delete('/managers/{itemId}', [AppOrganizerAdminController::class, 'destroyManager'])->whereNumber('itemId')->name('api.app.admin.managers.destroy');
            Route::post('/events', [AppOrganizerAdminController::class, 'storeEvent'])->name('api.app.admin.events.store');
            Route::post('/events/discover', [AppOrganizerAdminController::class, 'discover'])->middleware('throttle:5,1')->name('api.app.admin.events.discover');
            Route::post('/cache/purge', [AppOrganizerAdminController::class, 'purge'])->name('api.app.admin.cache.purge');
            Route::post('/data/refresh', [AppOrganizerAdminController::class, 'refreshData'])->name('api.app.admin.data.refresh');
        });
    });

    // The native app read-only event data (same sources as the website pages).
    Route::get('/app/events', [AppEventController::class, 'index'])->name('api.app.events');
    Route::prefix('app/events/{event:slug}')->group(function () {
        Route::get('/memories', [AppEventController::class, 'memories'])->name('api.app.events.memories');

        Route::middleware('event.public')->group(function () {
            Route::get('/', [AppEventController::class, 'show'])->name('api.app.events.show');
            Route::get('/sessions', [AppEventController::class, 'sessions'])->name('api.app.events.sessions');
            Route::get('/speakers', [AppEventController::class, 'speakers'])->name('api.app.events.speakers');
            Route::get('/sponsors', [AppEventController::class, 'sponsors'])->name('api.app.events.sponsors');
            Route::get('/organizers', [AppEventController::class, 'organizers'])->name('api.app.events.organizers');
            Route::get('/deals', [AppEventController::class, 'deals'])->name('api.app.events.deals');
            Route::get('/free-steals', [AppEventController::class, 'freeSteals'])->name('api.app.events.free-steals');
            Route::get('/quests', [AppEventController::class, 'quests'])->name('api.app.events.quests');
            Route::get('/contributor-tables', [AppEventController::class, 'tables'])->name('api.app.events.tables');
            Route::get('/info', [AppEventController::class, 'info'])->name('api.app.events.info');
            Route::get('/guide', [AppEventController::class, 'guide'])->name('api.app.events.guide');
            Route::get('/delay', [AppEventController::class, 'delay'])->name('api.app.events.delay');
            Route::get('/bundle', [AppEventController::class, 'bundle'])->name('api.app.events.bundle');
        });
    });

    Route::prefix('events/{event:slug}')->middleware('event.public')->group(function () {
        Route::get('/roster', RosterController::class)->name('api.events.roster');
        Route::get('/data-version', DataVersionController::class)->name('api.events.data-version');
        // Live announcements from the organizers (EventLiveController): the banner, and "this device has the event open".
        Route::get('/live-notice', [\App\Http\Controllers\Api\EventLiveController::class, 'notice'])->name('api.events.live-notice');

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
            Route::post('/seen', [\App\Http\Controllers\Api\EventLiveController::class, 'seen'])->name('api.events.seen');
            // Native app (FCM) token, kept apart from the Web Push table.
            Route::post('/push/native-subscribe', [NativePushTokenController::class, 'store'])->name('api.push.native-subscribe');
            Route::delete('/push/native-subscribe', [NativePushTokenController::class, 'destroy'])->name('api.push.native-unsubscribe');
            Route::post('/discovery', [DiscoveryController::class, 'store'])->name('api.discovery.store');
            Route::patch('/discovery/{discoveryId}', [DiscoveryController::class, 'update'])->name('api.discovery.update');
            Route::delete('/discovery/{discoveryId}', [DiscoveryController::class, 'destroy'])->name('api.discovery.destroy');
            Route::post('/discovery/{discoveryId}/waves', [DiscoveryWaveController::class, 'store'])->name('api.discovery.waves.store');
            Route::post('/discovery/{discoveryId}/report', [DiscoveryReportController::class, 'store'])->name('api.discovery.report');
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

Route::middleware('throttle:api-general')->get('/legal/{slug}', [LegalApiController::class, 'show'])->whereIn('slug', ['privacy', 'terms']);

