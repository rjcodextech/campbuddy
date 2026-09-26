<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The shell of every event manager page (<x-manager-layout>): a slim top bar
 * with sign-out, then the page's own title, breadcrumbs and actions. Kept apart
 * from the admin's <x-app-layout>, whose sidebar is all admin-only links.
 */
class ManagerLayout extends Component
{
    /**
     * @param  array<int, array{0: string, 1?: string}>  $breadcrumbs  [label, url] pairs; the last one (the current page) needs no url
     */
    public function __construct(
        public ?string $title = null,
        public ?string $subtitle = null,
        public array $breadcrumbs = [],
    ) {}

    public function render(): View
    {
        return view('layouts.manager');
    }
}
