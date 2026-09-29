<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreeStealRequest;
use App\Models\FreeSteal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Free Steals: one list for every event (Explore → Free Steals). The
 * collection can be bigger than what attendees see — the first
 * FreeSteal::SHOWN switched-on ones, by order.
 */
class FreeStealController extends Controller
{
    public function index(): View
    {
        $steals = FreeSteal::ordered()->get();
        $shownIds = FreeSteal::shown()->pluck('id');

        return view('admin.free-steals.index', compact('steals', 'shownIds'));
    }

    public function create(): View
    {
        return $this->form(new FreeSteal(['is_active' => true]));
    }

    public function store(StoreFreeStealRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] ??= (FreeSteal::max('sort_order') ?? 0) + 10;

        FreeSteal::create($data);

        return redirect()->route('admin.free-steals.index')->with('status', 'Free Steal added.');
    }

    public function edit(FreeSteal $freeSteal): View
    {
        return $this->form($freeSteal);
    }

    public function update(StoreFreeStealRequest $request, FreeSteal $freeSteal): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] ??= $freeSteal->sort_order;

        $freeSteal->update($data);

        return redirect()->route('admin.free-steals.index')->with('status', 'Free Steal updated.');
    }

    public function destroy(FreeSteal $freeSteal): RedirectResponse
    {
        $freeSteal->delete();

        return redirect()->route('admin.free-steals.index')->with('status', 'Free Steal removed.');
    }

    private function form(FreeSteal $steal): View
    {
        return view('admin.free-steals.form', [
            'steal' => $steal,
            'action' => $steal->exists ? route('admin.free-steals.update', $steal) : route('admin.free-steals.store'),
        ]);
    }
}
