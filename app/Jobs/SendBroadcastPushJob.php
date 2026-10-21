<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\PushSubscription;
use App\Support\ConcurrentWebPush;
use App\Support\EventBroadcasts;
use App\Support\NativePush;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * One live announcement (App\Support\EventBroadcasts) to every browser and app
 * install that had the event open since three days before it. Short-lived on
 * purpose: "tea break" an hour late is noise, so a phone that is off when it
 * goes out does not get it later.
 */
class SendBroadcastPushJob implements ShouldQueue
{
    use Queueable;

    public const TTL_SECONDS = 1800;

    public function __construct(public int $broadcastId) {}

    public function handle(): void
    {
        $row = DB::table('event_broadcasts')->find($this->broadcastId);
        $event = $row ? Event::find($row->event_id) : null;
        if (! $row || ! $event) {
            return;
        }

        $payload = [
            'title' => $row->title,
            'body' => $row->body,
            'url' => route('event.home', $event).($row->tab ? '/'.$row->tab : '').'?from=announcement',
        ];
        $json = json_encode($payload);
        $options = ['topic' => substr(hash('sha256', 'broadcast-'.$row->id), 0, 24), 'urgency' => 'high'];
        $since = EventBroadcasts::windowStart($event);
        $devices = EventBroadcasts::audience($event);
        $webPush = $this->webPush();
        $sent = 0;

        EventBroadcasts::webQuery($event, $devices, $since)->orderBy('id')->chunkById(200, function ($subs) use ($webPush, $json, $options, &$sent) {
            foreach ($subs as $sub) {
                try {
                    $webPush->queueNotification(Subscription::create(['endpoint' => $sub->endpoint, 'keys' => ['p256dh' => $sub->p256dh_key, 'auth' => $sub->auth_key]]), $json, $options);
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('Broadcast push queue failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
                }
            }
            try {
                foreach ($webPush->flush() as $report) {
                    if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Broadcast push batch failed', ['error' => $e->getMessage()]);
            }
        });

        EventBroadcasts::appQuery($event, $devices, $since)->orderBy('id')->chunkById(500, function ($tokens) use ($payload, $row, &$sent) {
            $sent += NativePush::toTokens($tokens, $payload, 'broadcast-'.$row->id, self::TTL_SECONDS)['sent'];
        });

        DB::table('event_broadcasts')->where('id', $row->id)->update(['recipients' => $sent, 'updated_at' => now()]);
        Log::info('Live announcement sent', ['event' => $event->slug, 'broadcast' => $row->id, 'phones' => $sent]);
    }

    protected function webPush(): WebPush
    {
        $webPush = new ConcurrentWebPush(
            ['VAPID' => [
                'subject' => config('services.vapid.subject'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ]],
            ['TTL' => self::TTL_SECONDS],
            app()->bound(Client::class) ? app(Client::class) : null
        );
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }
}
