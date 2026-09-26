<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventManagerRequest;
use App\Http\Requests\UpdateEventManagerRequest;
use App\Models\Event;
use App\Models\EventManager;
use App\Support\EventListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admins create and manage event managers — people who may edit a few
 * sections of the events they are given (Manager\* controllers). There is no
 * sign-up: this is the only way an account comes to exist.
 */
class EventManagerController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', EventManager::class);

        $managers = EventManager::with(['events' => fn ($q) => $q->select('events.id', 'events.display_name')->orderBy('events.display_name')])
            ->orderBy('name')
            ->paginate(25);

        return view('admin.event-managers.index', compact('managers'));
    }

    public function create(): View
    {
        Gate::authorize('create', EventManager::class);

        return view('admin.event-managers.create', $this->formData(new EventManager));
    }

    public function store(StoreEventManagerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $manager = EventManager::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'is_active' => $request->boolean('is_active', true),
        ]);
        $manager->events()->sync($data['events']);

        return redirect()
            ->route('admin.event-managers.edit', $manager)
            ->with('status', "Event manager “{$manager->name}” created. They sign in at ".route('manager.login').'.');
    }

    public function edit(EventManager $eventManager): View
    {
        Gate::authorize('update', $eventManager);

        return view('admin.event-managers.edit', $this->formData($eventManager));
    }

    public function update(UpdateEventManagerRequest $request, EventManager $eventManager): RedirectResponse
    {
        $data = $request->validated();

        $eventManager->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if (filled($data['password'] ?? null)) {
            $eventManager->password = $data['password'];
            // A "remember me" cookie from before the change stops working; the
            // session check (EnsureEventManager) signs out the open sessions.
            $eventManager->setRememberToken(Str::random(60));
        }

        $eventManager->save();
        $eventManager->events()->sync($data['events'] ?? []);

        return redirect()
            ->route('admin.event-managers.edit', $eventManager)
            ->with('status', "Event manager “{$eventManager->name}” updated.");
    }

    public function destroy(EventManager $eventManager): RedirectResponse
    {
        Gate::authorize('delete', $eventManager);

        $eventManager->delete();

        return redirect()
            ->route('admin.event-managers.index')
            ->with('status', "Event manager “{$eventManager->name}” deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(EventManager $manager): array
    {
        return [
            'manager' => $manager,
            // Every event, soonest first, so the one that matters is near the top.
            'events' => EventListing::ordered(Event::query())->get(['id', 'display_name', 'slug', 'status', 'starts_on', 'ends_on']),
            'assigned' => $manager->exists ? $manager->events()->pluck('events.id')->all() : [],
        ];
    }
}
