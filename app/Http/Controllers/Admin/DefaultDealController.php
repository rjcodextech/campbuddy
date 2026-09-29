<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfferRequest;
use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\Offer;
use App\Models\OfferLead;
use App\Support\DataVersion;
use App\Support\DealLeadCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Default deals: made once, shown at every event in their countries — the
 * events there today and every one discovered later (Offer::shownAt). An
 * event can still hide one from its own Deals page. Leads keep the event
 * they came from, so this page can list them across all events.
 */
class DefaultDealController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Offer::class);

        $deals = Offer::defaults()->with('mediaAsset')->withCount(['leads', 'hiddenAtEvents'])->orderBy('sort_order')->orderBy('id')->get();

        // How many upcoming events each deal reaches right now.
        $events = Event::whereIn('status', ['draft', 'approved', 'active'])->get();
        $reach = $deals->mapWithKeys(fn (Offer $deal) => [
            $deal->id => $events->filter(fn (Event $event) => $deal->coversCountryOf($event))->count() - $deal->hidden_at_events_count,
        ]);

        return view('admin.default-deals.index', compact('deals', 'reach'));
    }

    public function create(): View
    {
        Gate::authorize('create', Offer::class);

        return $this->form(new Offer(['is_active' => true, 'icon' => '🏷', 'countries' => ['IN']]));
    }

    public function store(StoreOfferRequest $request): RedirectResponse
    {
        $data = $request->deal(isDefault: true);
        $data['sort_order'] ??= (Offer::defaults()->max('sort_order') ?? 0) + 10;

        Offer::create($data);
        $this->forgetEvents();

        return redirect()->route('admin.deals.index')->with('status', 'Default deal added.');
    }

    public function edit(Offer $deal): View
    {
        $this->isDefault($deal);
        Gate::authorize('update', $deal);

        return $this->form($deal);
    }

    public function update(StoreOfferRequest $request, Offer $deal): RedirectResponse
    {
        $this->isDefault($deal);

        $deal->update($request->deal(isDefault: true));
        $this->forgetEvents();

        return redirect()->route('admin.deals.index')->with('status', 'Default deal updated.');
    }

    public function destroy(Offer $deal): RedirectResponse
    {
        $this->isDefault($deal);
        Gate::authorize('delete', $deal);

        $deal->delete();
        $this->forgetEvents();

        return redirect()->route('admin.deals.index')->with('status', 'Default deal removed.');
    }

    public function leads(Request $request, Offer $deal): View
    {
        $this->isDefault($deal);
        Gate::authorize('viewAny', OfferLead::class);

        $leads = $this->leadQuery($request, $deal)->latest()->paginate(50)->withQueryString();
        $events = Event::whereIn('id', $deal->leads()->select('event_id'))->orderBy('display_name')->pluck('display_name', 'id');

        return view('admin.default-deals.leads', [
            'deal' => $deal,
            'leads' => $leads,
            'events' => $events,
            'filters' => $request->only(['event_id', 'from', 'to']),
        ]);
    }

    public function export(Request $request, Offer $deal): StreamedResponse
    {
        $this->isDefault($deal);
        Gate::authorize('viewAny', OfferLead::class);

        return DealLeadCsv::download(
            $this->leadQuery($request, $deal)->latest()->get(),
            'deal-leads-'.\Illuminate\Support\Str::slug($deal->displayName()).'-'.now()->format('Y-m-d').'.csv'
        );
    }

    private function leadQuery(Request $request, Offer $deal)
    {
        return $deal->leads()
            ->with(['offer', 'event:id,display_name'])
            ->when($request->filled('event_id'), fn ($q) => $q->where('event_id', $request->integer('event_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));
    }

    private function form(Offer $deal): View
    {
        return view('admin.offers.form', [
            'event' => null,
            'offer' => $deal,
            'isDefault' => true,
            'mediaAssets' => MediaAsset::latest()->get(),
            'action' => $deal->exists ? route('admin.deals.update', $deal) : route('admin.deals.store'),
            'back' => route('admin.deals.index'),
        ]);
    }

    private function isDefault(Offer $deal): void
    {
        abort_unless($deal->isDefault(), 404);
    }

    /** A default deal can show at any event: open apps should see the change at their next check. */
    private function forgetEvents(): void
    {
        Event::whereIn('status', ['approved', 'active'])->pluck('id')->each(fn (int $id) => DataVersion::forget($id));
    }
}
