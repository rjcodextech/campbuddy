<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\QrKit;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin → Event → QR codes: the organizer QR kit (App\Support\QrKit). Read-only. */
class EventQrController extends Controller
{
    public function __invoke(Event $event): View
    {
        Gate::authorize('viewAny', Event::class);

        return view('admin.events.qr', ['event' => $event, 'codes' => QrKit::codes($event)]);
    }
}
