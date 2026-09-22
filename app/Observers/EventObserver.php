<?php

namespace App\Observers;

use App\Jobs\FetchBrandingAssetsJob;
use App\Models\Event;

class EventObserver
{
    /**
     * Branding auto-fetch runs once, when an event is first approved
     * (§0.6, §3.2 BR4) — not on every save, and not on the daily
     * ingestion schedule.
     */
    public function updated(Event $event): void
    {
        if (! $event->wasChanged('status')) {
            return;
        }

        $enteringApproved = $event->status === 'approved' && $event->getOriginal('status') !== 'approved';

        if ($enteringApproved && $event->logo_path === null && $event->favicon_path === null) {
            FetchBrandingAssetsJob::dispatch($event);
        }
    }
}
