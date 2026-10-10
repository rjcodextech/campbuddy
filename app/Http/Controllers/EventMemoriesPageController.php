<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\EventMemories;
use Illuminate\View\View;

/**
 * /event/{slug}/memories — the photo album and WordPress.tv talks, where the
 * Memories pushes land. Unlike the other event pages it stays open once the
 * event is archived; a draft or hidden event is still a 404.
 */
class EventMemoriesPageController extends Controller
{
    public function __invoke(Event $event): View
    {
        abort_unless(EventMemories::pageIsPublic($event), 404);

        return view('attendee.memories', [
            'event' => $event,
            'links' => EventMemories::links($event),
            'live' => $event->status === 'active',
        ]);
    }
}
