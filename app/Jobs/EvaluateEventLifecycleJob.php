<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FetchLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Daily lifecycle sweep, independent of the discovery cadence:
 *
 * 1. Archive: an active/approved event whose dates have passed (ends_on,
 *    falling back to starts_on when no end date is known — see
 *    DiscoverWordCampsJob, which can't always supply one) is done, so it
 *    moves to archived automatically. A draft event that's already past
 *    and never got promoted just stays a draft — nothing to archive,
 *    it was never live.
 * 2. Auto-publish: a still-upcoming draft is trusted enough to go live
 *    without manual admin approval once its own website is reachable
 *    AND it already has at least one public attendee — reuses
 *    ParseAttendeeRosterJob so the roster is actually ingested, not
 *    just probed, and skips the roster fetch entirely for a site
 *    that's unreachable.
 *
 * Neither rule ever touches an event outside these exact conditions —
 * this never demotes something an admin already approved/archived, and
 * never re-evaluates an event this job already promoted or archived.
 */
class EvaluateEventLifecycleJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $this->archiveCompletedEvents();
        $this->autoPublishReadyDrafts();
    }

    private function archiveCompletedEvents(): void
    {
        Event::whereIn('status', ['active', 'approved'])
            ->get()
            ->each(function (Event $event) {
                $endMarker = $event->ends_on ?? $event->starts_on;

                if (! $endMarker || ! $endMarker->copy()->endOfDay()->isPast()) {
                    return;
                }

                $event->update(['status' => 'archived']);

                FetchLog::create([
                    'event_id' => $event->id,
                    'source' => 'lifecycle',
                    'job_type' => 'lifecycle',
                    'status' => 'ok',
                    'message' => 'Archived automatically — event dates have passed.',
                    'fetched_at' => now(),
                ]);
            });
    }

    private function autoPublishReadyDrafts(): void
    {
        Event::where('status', 'draft')
            ->get()
            ->each(function (Event $event) {
                if (! $this->isReachable($event->source_site_url)) {
                    return;
                }

                try {
                    ParseAttendeeRosterJob::dispatchSync($event);
                } catch (Throwable) {
                    // Already logged by the roster job itself — treated
                    // as "not ready yet" here, not a reason to stop the
                    // sweep for the rest of the drafts.
                }

                $hasAttendee = $event->attendeeRoster()->where('is_suppressed', false)->exists();

                if (! $hasAttendee) {
                    return;
                }

                $event->update(['status' => 'active']);

                FetchLog::create([
                    'event_id' => $event->id,
                    'source' => 'lifecycle',
                    'job_type' => 'lifecycle',
                    'status' => 'ok',
                    'message' => 'Auto-published — public website reachable and at least one attendee found.',
                    'fetched_at' => now(),
                ]);
            });
    }

    private function isReachable(string $url): bool
    {
        try {
            return Http::timeout(10)->get($url)->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
