<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
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
        return view('layouts.app');
    }
}
