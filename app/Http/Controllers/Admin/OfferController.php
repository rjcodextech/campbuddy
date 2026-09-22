<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfferRequest;
use App\Models\Event;
use App\Models\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Offers/Deals, full CRUD, now event-scoped (§9, §3.7) — a deal doesn't
 * roll over to the next event unless resubmitted against it.
 */
class OfferController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('viewAny', Offer::class);

        $offers = $event->offers()->orderBy('sort_order')->get();

        return view('admin.offers.index', compact('event', 'offers'));
    }

    public function store(StoreOfferRequest $request, Event $event): RedirectResponse
    {
        $event->offers()->create($request->validated());

        return redirect()->route('admin.events.offers.index', $event)->with('status', 'Offer added.');
    }

    public function update(StoreOfferRequest $request, Event $event, Offer $offer): RedirectResponse
    {
        $offer->update($request->validated());

        return redirect()->route('admin.events.offers.index', $event)->with('status', 'Offer updated.');
    }

    public function destroy(Event $event, Offer $offer): RedirectResponse
    {
        Gate::authorize('delete', $offer);

        $offer->delete();

        return redirect()->route('admin.events.offers.index', $event)->with('status', 'Offer removed.');
    }
}
