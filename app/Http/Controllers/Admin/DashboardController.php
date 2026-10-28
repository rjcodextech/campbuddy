<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\FetchLog;
use App\Models\OfferLead;
use App\Models\SponsorDealSubmission;
use App\Support\CacheVersion;
use App\Support\ErrorReport;
use Illuminate\View\View;

/**
 * The admin landing page: only what needs a decision or a look today —
 * what's failing, drafts waiting for approval, the next WordCamps with a
 * countdown, three headline numbers, and the two maintenance buttons.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->toDateString();

        // Live events that haven't ended yet, soonest first. An event with
        // no end date counts as over once its start date has passed.
        $upcoming = Event::query()
            ->whereIn('status', ['approved', 'active'])
            ->whereNotNull('starts_on')
            ->whereRaw('COALESCE(ends_on, starts_on) >= ?', [$today])
            ->orderBy('starts_on');

        $nextEvents = (clone $upcoming)->withCount(['attendeeRoster as roster_count' => fn ($q) => $q->where('is_suppressed', false)])
            ->limit(4)
            ->get();

        return view('dashboard', [
            // What's wrong lives on the Errors page; the dashboard shows one line.
            'errorSummary' => ErrorReport::summary(),
            'pendingDealSubmissionsCount' => SponsorDealSubmission::pending()->count(),
            'draftCount' => Event::where('status', 'draft')->count(),
            'draftEvents' => Event::where('status', 'draft')->latest()->limit(5)->get(),
            'upcomingCount' => (clone $upcoming)->count(),
            'nextEvents' => $nextEvents,
            'rosterCount' => AttendeeRoster::where('is_suppressed', false)->count(),
            'leadCount' => OfferLead::count(),
            'recentLeadCount' => OfferLead::where('created_at', '>=', now()->subDays(7))->count(),
            'lastPurge' => CacheVersion::last(),
            'cloudflareConfigured' => filled(config('services.cloudflare.zone_id')) && filled(config('services.cloudflare.api_token')),
            'lastDataFetch' => FetchLog::with('event:id,display_name')->where('job_type', 'sessions_speakers_sponsors')->latest('fetched_at')->first(),
        ]);
    }
}
