<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\EventMemories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin → Event → Memories (App\Support\EventMemories). */
class MemoriesController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('viewAny', Event::class);

        return view('admin.events.memories', ['event' => $event]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        $summary = EventMemories::save($request, $event);

        return redirect()->route('admin.events.memories', $event)->with('status', $summary.'.');
    }

    public function notify(Request $request, Event $event, string $kind): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless(array_key_exists($kind, EventMemories::KINDS), 404);
        EventMemories::notify($event, $kind);

        return redirect()->route('admin.events.memories', $event)
            ->with('status', EventMemories::KINDS[$kind]['label'].': notification sent to '.EventMemories::recipients($event).' phones.');
    }
}
