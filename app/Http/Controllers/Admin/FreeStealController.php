<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreeStealRequest;
use App\Models\Event;
use App\Models\FreeSteal;
use App\Models\FreeStealSuggestion;
use App\Support\DataVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Free Steals: one list for every event (Explore → Free Steals). The
 * collection can be bigger than what attendees see — the first
 * FreeSteal::SHOWN switched-on ones, by order. Suggestions sent from the
 * app wait here until an admin adds one as a Free Steal or dismisses it.
 */
class FreeStealController extends Controller
{
    public function index(): View
    {
        $steals = FreeSteal::ordered()->get();
        $shownIds = FreeSteal::shown()->pluck('id');
        $suggestions = FreeStealSuggestion::with('event:id,display_name')->latest()->get();

        return view('admin.free-steals.index', compact('steals', 'shownIds', 'suggestions'));
    }

    public function create(Request $request): View
    {
        $steal = new FreeSteal(['is_active' => true]);

        // "Add" on a suggestion: start from what was sent.
        $suggestion = $request->filled('suggestion') ? FreeStealSuggestion::find($request->integer('suggestion')) : null;
        if ($suggestion) {
            $steal->fill(['name' => $suggestion->name, 'url' => $suggestion->url, 'maker' => $suggestion->maker, 'description' => $suggestion->why]);
        }

        return $this->form($steal, $suggestion);
    }

    public function store(StoreFreeStealRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('suggestion_id');
        $data['sort_order'] ??= (FreeSteal::max('sort_order') ?? 0) + 10;

        FreeSteal::create($data);
        if ($request->filled('suggestion_id')) {
            FreeStealSuggestion::whereKey($request->integer('suggestion_id'))->delete();
        }
        $this->forgetEvents();

        return redirect()->route('admin.free-steals.index')->with('status', 'Free Steal added.');
    }

    public function edit(FreeSteal $freeSteal): View
    {
        return $this->form($freeSteal);
    }

    public function update(StoreFreeStealRequest $request, FreeSteal $freeSteal): RedirectResponse
    {
        $data = $request->safe()->except('suggestion_id');
        $data['sort_order'] ??= $freeSteal->sort_order;

        $freeSteal->update($data);
        $this->forgetEvents();

        return redirect()->route('admin.free-steals.index')->with('status', 'Free Steal updated.');
    }

    public function destroy(FreeSteal $freeSteal): RedirectResponse
    {
        $freeSteal->delete();
        $this->forgetEvents();

        return redirect()->route('admin.free-steals.index')->with('status', 'Free Steal removed.');
    }

    public function dismiss(FreeStealSuggestion $suggestion): RedirectResponse
    {
        $suggestion->delete();

        return redirect()->route('admin.free-steals.index')->with('status', 'Suggestion dismissed.');
    }

    private function form(FreeSteal $steal, ?FreeStealSuggestion $suggestion = null): View
    {
        return view('admin.free-steals.form', [
            'steal' => $steal,
            'suggestion' => $suggestion,
            'action' => $steal->exists ? route('admin.free-steals.update', $steal) : route('admin.free-steals.store'),
        ]);
    }

    /** Free Steals show at every event: open apps should see the change at their next check. */
    private function forgetEvents(): void
    {
        Event::whereIn('status', ['approved', 'active'])->pluck('id')->each(fn (int $id) => DataVersion::forget($id));
    }
}
