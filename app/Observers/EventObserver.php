<?php

namespace App\Observers;

use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchEventInfoJob;
use App\Models\Event;

class EventObserver
{
    /**
     * Every event — created by an admin or found by discovery — starts with
     * the default checklist already in place.
     */
    public function created(Event $event): void
    {
        $event->seedDefaultChecklist();
    }

    /**
     * Branding auto-fetch runs once, when an event is first approved —
     * not on every save, and not on the daily ingestion schedule.
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

        // Fill in Event Information as soon as an event is approved, so it's
        // already there when the admin looks (the daily run keeps it fresh).
        if ($enteringApproved) {
            FetchEventInfoJob::dispatch($event);
        }
    }
}
