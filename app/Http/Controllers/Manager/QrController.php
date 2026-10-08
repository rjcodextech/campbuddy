<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Support\QrKit;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** An event manager's "QR codes" — the same kit as the admin's, for their own events only. Read-only. */
class QrController extends Controller
{
    use ResolvesManagedEvent;

    public function __invoke(Request $request, string $eventId): View
    {
        $event = $this->managedEvent($request, $eventId);

        return view('manager.events.qr', ['event' => $event, 'codes' => QrKit::codes($event)]);
    }
}
