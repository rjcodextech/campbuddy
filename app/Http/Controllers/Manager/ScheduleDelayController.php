<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Support\ManagerActivity;
use App\Support\ScheduleDelay;
use App\Support\ScheduleDelayForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** An event manager's "Running late": the admin's page, for their own events; every change logged. */
class ScheduleDelayController extends Controller
{
    use ResolvesManagedEvent;

    public function index(Request $request, string $eventId): View
    {
        return view('manager.events.delay', ['event' => $this->managedEvent($request, $eventId)]);
    }

    public function store(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $form = ScheduleDelayForm::read($request, $event);
        $manager = $request->user(EnsureEventManager::GUARD);
        ScheduleDelay::set($event, $form['track'], $form['minutes'], $form['from'], $form['note'], 'manager: '.$manager->name);
        ManagerActivity::record($manager, $event, 'delay', 'updated', $form['summary']);

        return redirect()->route('manager.events.delay', $event)->with('status', $form['summary'].'.');
    }

    public function clear(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        ScheduleDelay::clear($event);
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'delay', 'removed', 'Back on time: cleared every delay');

        return redirect()->route('manager.events.delay', $event)->with('status', 'Back on time: every delay cleared.');
    }
}
