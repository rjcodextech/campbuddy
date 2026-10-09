<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\ScheduleDelay;
use App\Support\ScheduleDelayForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin → Event → Running late (App\Support\ScheduleDelay). */
class ScheduleDelayController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('viewAny', Event::class);

        return view('admin.events.delay', ['event' => $event]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        $form = ScheduleDelayForm::read($request, $event);
        ScheduleDelay::set($event, $form['track'], $form['minutes'], $form['from'], $form['note'], 'admin: '.$request->user()->name);

        return redirect()->route('admin.events.delay', $event)->with('status', $form['summary'].'.');
    }

    public function clear(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        ScheduleDelay::clear($event);

        return redirect()->route('admin.events.delay', $event)->with('status', 'Back on time: every delay cleared.');
    }
}
