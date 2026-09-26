<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\EventManagerChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * What event managers have changed, newest first — who, which event, which
 * section, and what (App\Support\ManagerActivity writes it; 90 days are kept).
 */
class EventManagerActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', EventManager::class);

        if (! Schema::hasTable('event_managers') || ! Schema::hasTable('event_manager_changes')) {
            return view('admin.event-managers.not-ready');
        }

        // Only whole numbers count as a filter; anything else is ignored, not trusted.
        $id = fn (string $key): ?int => ctype_digit((string) $request->query($key)) && (int) $request->query($key) > 0 ? (int) $request->query($key) : null;
        $filters = ['manager' => $id('manager'), 'event' => $id('event')];

        $changes = EventManagerChange::query()
            ->with('event:id,display_name')
            ->when($filters['manager'], fn ($q, int $managerId) => $q->where('event_manager_id', $managerId))
            ->when($filters['event'], fn ($q, int $eventId) => $q->where('event_id', $eventId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.event-managers.activity', [
            'changes' => $changes,
            'filters' => $filters,
            'managers' => EventManager::orderBy('name')->pluck('name', 'id'),
            'events' => Event::whereIn('id', EventManagerChange::query()->select('event_id'))->orderBy('display_name')->pluck('display_name', 'id'),
            'keepDays' => \App\Support\ManagerActivity::KEEP_DAYS,
        ]);
    }
}
