<?php

namespace App\Observers;

use App\Models\Event;
use App\Support\DataVersion;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EventObserver
{
    /**
     * Every event — created by an admin or found by discovery — starts with
     * the default checklist already in place. One created already live (an
     * admin picking "Active" on the create form) is ingested straight away.
     */
    public function created(Event $event): void
    {
        $event->seedDefaultChecklist();

        if ($event->isLive()) {
            $this->queueIngest($event);
        }
    }

    /**
     * Branding, event information and schedule data are fetched when an event
     * first goes live — not on every save, and not (for branding) on the
     * daily schedule. "Live" is approved OR active, reached from any state:
     * the lifecycle sweep moves drafts straight to active, so keying this on
     * the "approved" step alone left auto-published events with no logo, no
     * event information and (without the scheduler's next run) no schedule.
     */
    public function updated(Event $event): void
    {
        if (! $event->wasChanged('status')) {
            return;
        }

        $previous = $event->getOriginal('status');
        $wasLive = in_array($previous, ['approved', 'active'], true);

        if ($event->isLive() && ! $wasLive) {
            $this->queueIngest($event);

            return;
        }

        // approved → active: schedule and roster only start being needed now.
        if ($event->status === 'active' && $previous === 'approved') {
            $this->queueIngest($event);
        }
    }

    /**
     * The sitemap and llms.txt list live events — rebuild them on the next
     * request after any change, so a new or renamed WordCamp shows up at once.
     */
    public function saved(Event $event): void
    {
        Cache::forget('seo:sitemap');
        Cache::forget('seo:llms');
        DataVersion::forget($event->id);
    }

    public function deleted(Event $event): void
    {
        $this->saved($event);
    }

    /**
     * Never lets a queueing problem (an unreachable queue backend, or a
     * `sync` queue running a fetch inline and failing) break the admin's save
     * — the event is already stored; the scheduled runs and the admin's
     * manual buttons will fill in whatever didn't get queued.
     */
    private function queueIngest(Event $event): void
    {
        try {
            $event->queueInitialIngest();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
