<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\FetchLog;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Support\CacheVersion;
use App\Support\SystemHealth;
use Illuminate\View\View;

/**
 * The admin landing page: a glance at what needs attention (events waiting
 * for approval, ingestion that's failing) plus headline numbers.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $eventsByStatus = Event::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard', [
            'problems' => SystemHealth::problems(),
            'eventsByStatus' => $eventsByStatus,
            'eventCount' => $eventsByStatus->sum(),
            'rosterCount' => AttendeeRoster::where('is_suppressed', false)->count(),
            'activeDeals' => Offer::where('is_active', true)->count(),
            'leadCount' => OfferLead::count(),
            'recentLeadCount' => OfferLead::where('created_at', '>=', now()->subDays(7))->count(),
            'recentEvents' => Event::latest('updated_at')->limit(6)->get(),
            'draftEvents' => Event::where('status', 'draft')->latest()->limit(5)->get(),
            'lastPurge' => CacheVersion::last(),
            'cloudflareConfigured' => filled(config('services.cloudflare.zone_id')) && filled(config('services.cloudflare.api_token')),
            'lastDataFetch' => FetchLog::with('event:id,display_name')->where('job_type', 'sessions_speakers_sponsors')->latest('fetched_at')->first(),
            // The same failure repeating every run is one problem, not five rows.
            'failedFetches' => FetchLog::with('event:id,display_name')
                ->where('status', '!=', 'ok')
                ->where('fetched_at', '>=', now()->subDays(7))
                ->latest('fetched_at')
                ->limit(50)
                ->get()
                ->groupBy(fn ($log) => $log->event_id.'|'.$log->job_type.'|'.$log->message)
                ->map(fn ($logs) => ['log' => $logs->first(), 'times' => $logs->count()])
                ->take(5)
                ->values(),
        ]);
    }
}
