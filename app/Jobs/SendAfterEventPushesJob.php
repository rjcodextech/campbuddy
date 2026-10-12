<?php

namespace App\Jobs;

use App\Models\Event;
use App\Support\EventPush;
use App\Support\EventTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * The after-event pushes, each once per event, to every phone that turned
 * reminders on for it, in the venue's daytime (as the day-3 thank-you push,
 * SendThankYouPushJob):
 *
 *  - day 7, "save your data": the sessions, people and notes live only on the
 *    phone; one tap makes the PDF (?export=1 opens the thank-you card on it);
 *  - day 15, "take it with you": move CampBuddy to a new phone, or bring the
 *    profile and the people met to the next WordCamp (?move=1 opens the sheet
 *    that says how).
 *
 * The "sent" time is set before sending, so a crash can't double a push. An
 * event already past the retention window when this first sees it gets none.
 */
class SendAfterEventPushesJob implements ShouldQueue
{
    use Queueable;

    public const SAVE_AFTER_DAYS = 7;

    public const MOVE_AFTER_DAYS = 15;

    public const TTL_SECONDS = 86400 * 2;

    public function handle(): void
    {
        Event::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('save_data_sent_at')->orWhereNull('move_data_sent_at'))
            ->each(function (Event $event) {
                $days = EventTime::daysSinceEnd($event);
                if ($days === null || $days > EventTime::RETENTION_DAYS) {
                    return;
                }

                $hour = (int) now()->setTimezone(EventTime::zone($event))->format('G');
                if ($hour < SendThankYouPushJob::FROM_HOUR || $hour >= SendThankYouPushJob::UNTIL_HOUR) {
                    return;
                }

                // The later one first: an event first seen on day 16 gets only the day-15 push, not both at once.
                if ($days >= self::MOVE_AFTER_DAYS && $event->move_data_sent_at === null) {
                    $event->forceFill(['move_data_sent_at' => now(), 'save_data_sent_at' => $event->save_data_sent_at ?? now()])->save();
                    $this->send($event, 'move-data', [
                        'title' => 'New phone, or your next WordCamp?',
                        'body' => "Take {$event->display_name} with you: move CampBuddy to a new phone, or bring your profile and the people you met to your next WordCamp.",
                        'url' => route('event.home', $event).'?move=1',
                    ]);
                } elseif ($days >= self::SAVE_AFTER_DAYS && $days < self::MOVE_AFTER_DAYS && $event->save_data_sent_at === null) {
                    $event->forceFill(['save_data_sent_at' => now()])->save();
                    $this->send($event, 'save-data', [
                        'title' => "Keep your {$event->display_name}",
                        'body' => 'Your sessions, the people you met and your notes live only on this phone. Save them as a PDF in one tap.',
                        'url' => route('event.home', $event).'?export=1',
                    ]);
                }
            });
    }

    private function send(Event $event, string $kind, array $payload): void
    {
        $sent = EventPush::send($event, $payload, $kind, self::TTL_SECONDS);
        EventPush::log($event, $kind, $sent);
        Log::info('After-event push sent', ['event' => $event->slug, 'kind' => $kind, 'subscriptions' => $sent]);
    }
}
