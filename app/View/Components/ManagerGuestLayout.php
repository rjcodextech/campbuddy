<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/** The event manager sign-in page shell (<x-manager-guest-layout>). */
class ManagerGuestLayout extends Component
{
    public function __construct(public ?string $title = null, public ?string $subtitle = null) {}

    public function render(): View
    {
        return view('layouts.manager-guest');
    }
}
