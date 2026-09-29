<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Support\DealLeadCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leads captured via Offer::capture_leads (§7) — what an attendee filled in
 * before opening a deal with a contact form (DealForm). Event-scoped and
 * filterable the same way Roster is, plus a CSV export; the event's own
 * deals and the default deals it shows are both listed.
 */
class DealLeadController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        Gate::authorize('viewAny', OfferLead::class);

        $offers = $event->offers()->get()->concat(Offer::defaultsFor($event))
            ->sortBy(fn (Offer $offer) => mb_strtolower($offer->displayName()));

        $leads = $this->filteredQuery($request, $event)
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.deal-leads.index', [
            'event' => $event,
            'offers' => $offers,
            'leads' => $leads,
            'filters' => $request->only(['offer_id', 'from', 'to']),
        ]);
    }

    public function export(Request $request, Event $event): StreamedResponse
    {
        Gate::authorize('viewAny', OfferLead::class);

        return DealLeadCsv::download(
            $this->filteredQuery($request, $event)->latest()->get(),
            'deal-leads-'.$event->slug.'-'.now()->format('Y-m-d').'.csv'
        );
    }

    private function filteredQuery(Request $request, Event $event): Builder
    {
        return OfferLead::where('event_id', $event->id)
            ->with(['offer', 'event:id,display_name'])
            ->when($request->filled('offer_id'), fn ($q) => $q->where('offer_id', $request->integer('offer_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));
    }
}
