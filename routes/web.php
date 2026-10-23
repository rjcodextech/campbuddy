<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\CachePurgeController;
use App\Http\Controllers\Admin\CommandController;
use App\Http\Controllers\Admin\ContributorTableController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataRefreshController;
use App\Http\Controllers\Admin\DealLeadController;
use App\Http\Controllers\Admin\DefaultDealController;
use App\Http\Controllers\Admin\ErrorsController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventManagerActivityController;
use App\Http\Controllers\Admin\EventManagerController;
use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\FreeStealController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MobileAppController;
use App\Http\Controllers\Admin\MemoriesController;
use App\Http\Controllers\Admin\NotificationsController;
use App\Http\Controllers\Admin\OfferController;
use App\Http\Controllers\Admin\QuestController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\RosterController;
use App\Http\Controllers\Admin\ScheduleDelayController;
use App\Http\Controllers\Admin\SocialController;
use App\Http\Controllers\Admin\UsagePostController;
use App\Http\Controllers\AppPushController;
use App\Http\Controllers\CalendarFileController;
use App\Http\Controllers\EventMemoriesPageController;
use App\Http\Controllers\EventPageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\RosterRemovalController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\WellKnownController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| Attendee-facing routes
|--------------------------------------------------------------------------
| Built out per event across the tabs (Home, My Day, Quest, Contribute, Explore,
| Camp Card) — phases 4-11. {event} here binds by slug and is scoped
| to public visibility inside EventPageController — deliberately not a
| global Route::bind(), since the /admin/events/{event} routes reuse the
| same parameter name but must resolve ANY event regardless of status.
*/
// Attendees never log in and these routes only read, so they run without a
// session, cookies or CSRF check. With the web group's defaults every page view
// (and every image the storage route serves) wrote a session row and sent two
// Set-Cookie headers to someone who has no account and never will.
Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
])->group(function () {
    Route::get('/', HomeController::class)->name('home');

    // For search engines and AI assistants (SeoController) — built from live
    // data, so a new WordCamp is listed the moment it goes live.
    Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
    Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
    Route::get('/llms.txt', [SeoController::class, 'llms'])->name('llms');
    // "Add to calendar" on iPhone: echoes a calendar file built on the device
    // back with the headers Safari needs (CalendarFileController). Stateless.
    Route::post('/calendar.ics', CalendarFileController::class)->middleware('throttle:30,1')->name('calendar.file');
    Route::get('/indexnow-{key}.txt', [SeoController::class, 'indexNowKey'])->where('key', '[A-Za-z0-9-]{8,128}')->name('indexnow.key');

    // WordCamp 101 for first-timers, before any event is picked. Each event
    // has its own copy (event.guide) that adds that event's venue and times.
    Route::view('/guide', 'attendee.guide-general')->name('guide');

    // CampBuddy's brand guidelines, drawn with the live tokens (CAMPBUDDY-BRAND-GUIDELINES.md).
    Route::view('/brand-guidelines', 'brand.guidelines')->name('brand-guidelines');

    // What CampBuddy does with personal data (DPDPA notice) and the rules of use.
    Route::get('/privacy', [\App\Http\Controllers\LegalPageController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [\App\Http\Controllers\LegalPageController::class, 'terms'])->name('terms');

    // The newest Android APK (Admin → Mobile app → App updates): the link to share for a first install.
    Route::get('/app/android', [\App\Http\Controllers\Api\AppUpdateController::class, 'latest'])->name('app.android');

    // PWA manifest for the picker page and any page without an event of its own.
    Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');

    // Links that open in the native app when it is installed (Android App Links, iOS Universal Links).
    Route::get('/.well-known/assetlinks.json', [WellKnownController::class, 'assetLinks'])->name('well-known.assetlinks');
    Route::get('/.well-known/apple-app-site-association', [WellKnownController::class, 'appleAppSiteAssociation'])->name('well-known.aasa');

    // Serves the public disk when the web server doesn't do it itself (no
    // public/storage symlink — see PublicStorageController). Files that exist on
    // disk under public/ are served by the web server before PHP ever runs.
    Route::get('/storage/{path}', PublicStorageController::class)->where('path', '.+')->name('storage.public');

    Route::middleware('event.public')->group(function () {
        Route::get('/event/{event:slug}', [EventPageController::class, 'home'])->name('event.home');
        Route::get('/event/{event:slug}/my-day', [EventPageController::class, 'myDay'])->name('event.my-day');
        Route::get('/event/{event:slug}/quest', [EventPageController::class, 'quest'])->name('event.quest');
        Route::get('/event/{event:slug}/contribute', [EventPageController::class, 'contribute'])->name('event.contribute');
        Route::get('/event/{event:slug}/explore', [EventPageController::class, 'explore'])->name('event.explore');
        Route::get('/event/{event:slug}/camp-card', [EventPageController::class, 'campCard'])->name('event.camp-card');
        Route::get('/event/{event:slug}/guide', [EventPageController::class, 'guide'])->name('event.guide');
        Route::get('/event/{event:slug}/manifest.json', ManifestController::class)->name('event.manifest');
    });

    // The photo album and WordPress.tv talks (App\Support\EventMemories). Not behind
    // event.public: it stays open after the event is archived, because the videos
    // often arrive weeks later. The controller still 404s a draft or hidden event.
    Route::get('/event/{event:slug}/memories', EventMemoriesPageController::class)->name('event.memories');
});

// The one attendee form that does need a session: CSRF protection and its
// "you've been removed" message.
Route::middleware('event.public')->group(function () {
    // The takedown path — public, no login, throttled against abuse.
    Route::middleware('throttle:20,1')->group(function () {
        Route::get('/event/{event:slug}/roster-removal', [RosterRemovalController::class, 'show'])->name('event.roster-removal.show');
        Route::get('/event/{event:slug}/roster-removal/search', [RosterRemovalController::class, 'search'])->name('event.roster-removal.search');
        Route::post('/event/{event:slug}/roster-removal/{entry}', [RosterRemovalController::class, 'remove'])->name('event.roster-removal.remove');
    });
});

/*
|--------------------------------------------------------------------------
| Admin panel — Breeze-scaffolded, session-based auth
|--------------------------------------------------------------------------
| Kept under /admin so it never bleeds into the attendee-facing app or its
| design system. Route *names* stay Breeze's defaults (login,
| dashboard, profile.*, ...) so the framework's own redirect targets
| (e.g. the Authenticate middleware's route('login')) resolve correctly
| without extra config — only the URI prefix changes.
*/
Route::prefix('admin')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        // The store app: this admin's phone for alerts (reports on a profile).
        Route::post('app-push', [AppPushController::class, 'store'])->name('admin.app-push.store');
        Route::delete('app-push', [AppPushController::class, 'destroy'])->name('admin.app-push.destroy');

        // The native app's server-driven settings: tabs, Home layout, texts, colours, banner, switches.
        Route::get('mobile-app', [MobileAppController::class, 'index'])->name('admin.mobile.index');
        Route::post('mobile-app/publish', [MobileAppController::class, 'publish'])->name('admin.mobile.publish');
        Route::post('mobile-app/discard', [MobileAppController::class, 'discard'])->name('admin.mobile.discard');
        Route::post('mobile-app/rollback/{version}', [MobileAppController::class, 'rollback'])->whereNumber('version')->name('admin.mobile.rollback');
        // Android app updates outside the Play Store (App updates tab).
        Route::post('mobile-app/releases/chunk', [\App\Http\Controllers\Admin\AppReleaseController::class, 'chunk'])->name('admin.mobile.releases.chunk');
        Route::post('mobile-app/releases', [\App\Http\Controllers\Admin\AppReleaseController::class, 'store'])->name('admin.mobile.releases.store');
        Route::post('mobile-app/releases/{release}/publish', [\App\Http\Controllers\Admin\AppReleaseController::class, 'publish'])->whereNumber('release')->name('admin.mobile.releases.publish');
        Route::post('mobile-app/releases/{release}/withdraw', [\App\Http\Controllers\Admin\AppReleaseController::class, 'withdraw'])->whereNumber('release')->name('admin.mobile.releases.withdraw');
        Route::delete('mobile-app/releases/{release}', [\App\Http\Controllers\Admin\AppReleaseController::class, 'destroy'])->whereNumber('release')->name('admin.mobile.releases.destroy');
        Route::get('mobile-app/releases/{release}/download', [\App\Http\Controllers\Admin\AppReleaseController::class, 'download'])->whereNumber('release')->name('admin.mobile.releases.download');
        Route::post('mobile-app/{section}', [MobileAppController::class, 'save'])->whereIn('section', ['navigation', 'home', 'text', 'look', 'banner', 'switches'])->name('admin.mobile.save');

        // Everything that is failing, in one place (the dashboard only summarises it).
        Route::get('errors', ErrorsController::class)->name('admin.errors.index');

        // Google Analytics, read here (one GA stream at a time; cached an hour).
        Route::get('analytics', AnalyticsController::class)->middleware('throttle:30,1')->name('admin.analytics.index');
        // A social media post about how one WordCamp used CampBuddy (numbers as JSON; drawn in the browser).
        Route::get('analytics/usage-post', UsagePostController::class)->middleware('throttle:20,1')->name('admin.analytics.usage-post');
        // What attendees said on the after-event thank-you card.
        Route::get('feedback', FeedbackController::class)->name('admin.feedback.index');
        // CampBuddy's artisan commands: what each does, and a Run form (one at a time).
        Route::get('commands', [CommandController::class, 'index'])->name('admin.commands.index');
        Route::post('commands/{name}', [CommandController::class, 'run'])->where('name', '[a-z0-9:-]+')->middleware('throttle:10,1')->name('admin.commands.run');

        // Legal documents (Privacy notice and Terms of use).
        Route::get('legal', [\App\Http\Controllers\Admin\LegalDocumentController::class, 'index'])->name('admin.legal.index');
        Route::get('legal/{slug}', [\App\Http\Controllers\Admin\LegalDocumentController::class, 'show'])->name('admin.legal.show');
        Route::get('legal/{slug}/edit', [\App\Http\Controllers\Admin\LegalDocumentController::class, 'edit'])->name('admin.legal.edit');
        Route::match(['put', 'post'], 'legal/{slug}', [\App\Http\Controllers\Admin\LegalDocumentController::class, 'update'])->name('admin.legal.update');
        Route::post('legal/{slug}/reset', [\App\Http\Controllers\Admin\LegalDocumentController::class, 'reset'])->name('admin.legal.reset');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Full CRUD on events — Create/Read/Update always available;
        // Delete is policy-gated to draft events only (EventPolicy::delete).
        // Event managers: people given a few events to edit (their own area is
        // routes/manager.php). Created here, never by sign-up.
        Route::get('event-managers/activity', EventManagerActivityController::class)->name('admin.event-managers.activity');
        Route::resource('event-managers', EventManagerController::class)->except('show')->names('admin.event-managers');

        Route::resource('events', EventController::class)->except('show')->names('admin.events');
        Route::post('events/discover', [EventController::class, 'discover'])->name('admin.events.discover');
        Route::post('events/{event}/refresh', [EventController::class, 'refresh'])->name('admin.events.refresh');
        Route::post('events/{event}/refresh-branding', [EventController::class, 'refreshBranding'])->name('admin.events.refresh-branding');
        Route::post('events/{event}/branding', [EventController::class, 'uploadBranding'])->name('admin.events.upload-branding');
        Route::put('events/{event}/info', [EventController::class, 'updateInfo'])->name('admin.events.update-info');
        Route::post('events/{event}/fetch-info', [EventController::class, 'fetchInfo'])->name('admin.events.fetch-info');

        Route::get('events/{event}/quests', [QuestController::class, 'index'])->name('admin.events.quests.index');
        Route::post('events/{event}/quests', [QuestController::class, 'store'])->name('admin.events.quests.store');
        Route::put('events/{event}/quests/{quest}', [QuestController::class, 'update'])->name('admin.events.quests.update');
        Route::delete('events/{event}/quests/{quest}', [QuestController::class, 'destroy'])->name('admin.events.quests.destroy');

        Route::get('events/{event}/offers', [OfferController::class, 'index'])->name('admin.events.offers.index');
        Route::get('events/{event}/offers/create', [OfferController::class, 'create'])->name('admin.events.offers.create');
        Route::post('events/{event}/offers', [OfferController::class, 'store'])->name('admin.events.offers.store');
        Route::get('events/{event}/offers/{offer}/edit', [OfferController::class, 'edit'])->name('admin.events.offers.edit');
        Route::put('events/{event}/offers/{offer}', [OfferController::class, 'update'])->name('admin.events.offers.update');
        Route::delete('events/{event}/offers/{offer}', [OfferController::class, 'destroy'])->name('admin.events.offers.destroy');
        Route::post('events/{event}/offers/{offer}/visibility', [OfferController::class, 'visibility'])->name('admin.events.offers.visibility');

        // Default deals: shown at every event in their countries (and every one found later).
        Route::resource('deals', DefaultDealController::class)->except('show')->names('admin.deals');
        Route::get('deals/{deal}/leads', [DefaultDealController::class, 'leads'])->name('admin.deals.leads');
        Route::get('deals/{deal}/leads/export', [DefaultDealController::class, 'export'])->name('admin.deals.leads.export');

        // Free Steals: hand-picked free plugins and tools, one list for every event.
        Route::resource('free-steals', FreeStealController::class)->except('show')->names('admin.free-steals');
        Route::delete('free-steal-suggestions/{suggestion}', [FreeStealController::class, 'dismiss'])->name('admin.free-steals.suggestions.dismiss');

        Route::get('events/{event}/roster', [RosterController::class, 'index'])->name('admin.events.roster.index');
        Route::get('events/{event}/qr', [SocialController::class, 'qr'])->name('admin.events.qr');
        Route::get('events/{event}/social', [SocialController::class, 'index'])->name('admin.events.social');
        Route::post('events/{event}/social/colors', [SocialController::class, 'colors'])->name('admin.events.social.colors');
        Route::get('events/{event}/social/image', [SocialController::class, 'image'])->name('admin.events.social.image');
        Route::post('events/{event}/social/site', [SocialController::class, 'site'])->name('admin.events.social.site');
        Route::post('events/{event}/social/art', [SocialController::class, 'art'])->middleware('throttle:20,1')->name('admin.events.social.art');
        Route::post('events/{event}/social/art/remove', [SocialController::class, 'removeArt'])->name('admin.events.social.art.remove');
        Route::post('events/{event}/social/art/use', [SocialController::class, 'useArt'])->name('admin.events.social.art.use');
        Route::post('events/{event}/social/art/kind', [SocialController::class, 'artKind'])->name('admin.events.social.art.kind');
        Route::post('events/{event}/social/webhook', [SocialController::class, 'webhook'])->name('admin.events.social.webhook');
        Route::post('events/{event}/social/publish', [SocialController::class, 'publish'])->middleware('throttle:20,1')->name('admin.events.social.publish');
        // "Remind me on my phone when a post is due": each admin for themselves.
        Route::post('social/reminders', [SocialController::class, 'reminders'])->name('admin.social.reminders');
        Route::get('events/{event}/delay', [ScheduleDelayController::class, 'index'])->name('admin.events.delay');
        Route::post('events/{event}/delay', [ScheduleDelayController::class, 'store'])->name('admin.events.delay.store');
        Route::post('events/{event}/delay/clear', [ScheduleDelayController::class, 'clear'])->name('admin.events.delay.clear');
        Route::get('events/{event}/memories', [MemoriesController::class, 'index'])->name('admin.events.memories');
        Route::get('events/{event}/notifications', NotificationsController::class)->name('admin.events.notifications');
        Route::post('events/{event}/broadcasts', [\App\Http\Controllers\Admin\BroadcastController::class, 'store'])->middleware('throttle:10,1')->name('admin.events.broadcasts.store');
        Route::get('events/{event}/reports', [ReportsController::class, 'index'])->name('admin.events.reports');
        Route::post('events/{event}/reports/{discoveryId}/remove', [ReportsController::class, 'remove'])->where('discoveryId', '[a-f0-9]{64}')->name('admin.events.reports.remove');
        Route::post('events/{event}/reports/{discoveryId}/dismiss', [ReportsController::class, 'dismiss'])->where('discoveryId', '[a-f0-9]{64}')->name('admin.events.reports.dismiss');
        Route::post('events/{event}/memories', [MemoriesController::class, 'store'])->name('admin.events.memories.store');
        Route::post('events/{event}/memories/notify/{kind}', [MemoriesController::class, 'notify'])->whereIn('kind', ['photos', 'videos'])->middleware('throttle:10,1')->name('admin.events.memories.notify');
        Route::get('events/{event}/tables', [ContributorTableController::class, 'index'])->name('admin.events.tables.index');
        Route::post('events/{event}/tables', [ContributorTableController::class, 'store'])->name('admin.events.tables.store');
        Route::put('events/{event}/tables/{table}', [ContributorTableController::class, 'update'])->name('admin.events.tables.update');
        Route::delete('events/{event}/tables/{table}', [ContributorTableController::class, 'destroy'])->name('admin.events.tables.destroy');
        Route::post('events/{event}/roster/{entry}/suppress', [RosterController::class, 'suppress'])->name('admin.events.roster.suppress');
        Route::post('events/{event}/roster/{entry}/unsuppress', [RosterController::class, 'unsuppress'])->name('admin.events.roster.unsuppress');
        Route::post('events/{event}/roster/{entry}/release-claim', [RosterController::class, 'releaseClaim'])->name('admin.events.roster.release-claim');
        Route::post('events/{event}/roster/{entry}/roles', [RosterController::class, 'roles'])->name('admin.events.roster.roles');
        Route::post('events/{event}/roster/{entry}/remove-card', [RosterController::class, 'removeCard'])->name('admin.events.roster.remove-card');

        Route::get('events/{event}/deal-leads', [DealLeadController::class, 'index'])->name('admin.events.deal-leads.index');
        Route::get('events/{event}/deal-leads/export', [DealLeadController::class, 'export'])->name('admin.events.deal-leads.export');

        // Two separate jobs. "Clear cache": server caches + Cloudflare, and tells open
        // apps to drop saved copies — fetches nothing. Rate-limited inside the controller
        // (a cooldown + lock), not with `throttle` — a purge empties the cache that throttle counts in.
        Route::post('cache/purge', CachePurgeController::class)->name('admin.cache.purge');
        // "Refresh event data": fetch every live event's data now — clears nothing.
        Route::post('data/refresh', DataRefreshController::class)->name('admin.data.refresh');

        Route::get('media', [MediaController::class, 'index'])->name('admin.media.index');
        Route::post('media', [MediaController::class, 'store'])->name('admin.media.store');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('admin.media.destroy');
    });

    require __DIR__.'/auth.php';
});

/*
|--------------------------------------------------------------------------
| Event manager area — sign-in and the three sections a manager may edit
|--------------------------------------------------------------------------
*/
require __DIR__.'/manager.php';
