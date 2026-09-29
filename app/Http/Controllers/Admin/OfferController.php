<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfferRequest;
use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\Offer;
use App\Support\DataVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Offers/Deals, full CRUD, now event-scoped — a deal doesn't
 * roll over to the next event unless resubmitted against it. Default deals
 * (Admin → Default deals, DefaultDealController) show here too, where the
 * event can hide one.
 */
class OfferController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('viewAny', Offer::class);

        $offers = $event->offers()->with('mediaAsset')->withCount('leads')->orderBy('sort_order')->get();
        $defaults = Offer::defaultsFor($event);

        return view('admin.offers.index', compact('event', 'offers', 'defaults'));
    }

    public function create(Event $event): View
    {
        Gate::authorize('create', Offer::class);

        return $this->form($event, new Offer(['is_active' => true, 'icon' => '🏷']));
    }

    public function store(StoreOfferRequest $request, Event $event): RedirectResponse
    {
        $data = $request->deal(isDefault: false);

        // New deals go to the end of the list unless the admin picked a position.
        $data['sort_order'] ??= ($event->offers()->max('sort_order') ?? 0) + 10;

        $event->offers()->create($data);
        DataVersion::forget($event->id);

        return redirect()->route('admin.events.offers.index', $event)->with('status', 'Offer added.');
    }

    public function edit(Event $event, Offer $offer): View
    {
        $this->ownedBy($event, $offer);
        Gate::authorize('update', $offer);

        return $this->form($event, $offer);
    }

    public function update(StoreOfferRequest $request, Event $event, Offer $offer): RedirectResponse
    {
        $this->ownedBy($event, $offer);

        $offer->update($request->deal(isDefault: false));
        DataVersion::forget($event->id);

        return redirect()->route('admin.events.offers.index', $event)->with('status', 'Offer updated.');
    }

    public function destroy(Event $event, Offer $offer): RedirectResponse
    {
        $this->ownedBy($event, $offer);
        Gate::authorize('delete', $offer);

        $offer->delete();
        DataVersion::forget($event->id);

        return redirect()->route('admin.events.offers.index', $event)->with('status', 'Offer removed.');
    }

    /** Hide a default deal at this event, or show it again. */
    public function visibility(Request $request, Event $event, Offer $offer): RedirectResponse
    {
        Gate::authorize('update', $offer);
        abort_unless($offer->isDefault() && $offer->coversCountryOf($event), 404);

        if ($request->boolean('hidden')) {
            $offer->hiddenAtEvents()->syncWithoutDetaching([$event->id]);
            $message = "“{$offer->displayName()}” is hidden at this event.";
        } else {
            $offer->hiddenAtEvents()->detach($event->id);
            $message = "“{$offer->displayName()}” is shown at this event again.";
        }
        DataVersion::forget($event->id);

        return redirect()->route('admin.events.offers.index', $event)->with('status', $message);
    }

    private function form(Event $event, Offer $offer): View
    {
        return view('admin.offers.form', [
            'event' => $event,
            'offer' => $offer,
            'isDefault' => false,
            'mediaAssets' => MediaAsset::latest()->get(),
            'action' => $offer->exists ? route('admin.events.offers.update', [$event, $offer]) : route('admin.events.offers.store', $event),
            'back' => route('admin.events.offers.index', $event),
        ]);
    }

    /** A deal is edited from its own event's page only (and a default deal from Default deals). */
    private function ownedBy(Event $event, Offer $offer): void
    {
        abort_unless($offer->event_id === $event->id, 404);
    }
}
