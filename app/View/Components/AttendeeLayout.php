<?php

namespace App\View\Components;

use App\Models\Event;
use Illuminate\View\Component;
use Illuminate\View\View;

class AttendeeLayout extends Component
{
    public function __construct(public Event $event) {}

    public function render(): View
    {
        return view('layouts.attendee');
    }
}
