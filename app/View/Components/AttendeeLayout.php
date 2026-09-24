<?php

namespace App\View\Components;

use App\Models\Event;
use Illuminate\View\Component;
use Illuminate\View\View;

class AttendeeLayout extends Component
{
    /**
     * @param  string|null  $title  The tab/page the attendee is on ("My Day",
     *                              "Explore"…) — leads the document title.
     */
    public function __construct(public Event $event, public ?string $title = null) {}

    public function render(): View
    {
        return view('layouts.attendee');
    }
}
