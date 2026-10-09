<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Publish" on the Social media page: one click hands a post to the
 * organizers' own Make.com / Zapier / n8n / Pipedream scenario, which posts it
 * to LinkedIn, Facebook, Instagram or X with their own accounts. CampBuddy
 * never holds a social network login.
 *
 * The image is saved as a public file (the scenario needs a link to it), and
 * the server — never the browser — calls the webhook, so its address stays
 * private. Only hosts of those automation services are accepted, so the
 * server can't be pointed at anything else.
 */
class SocialPublisher
{
    /** Hosts a webhook may be on (the address itself is the organizers' secret). */
    public const HOSTS = ['hooks.zapier.com', 'make.com', 'integromat.com', 'pipedream.net', 'n8n.cloud'];

    public static function configured(Event $event): bool
    {
        try {
            return filled($event->social_webhook);
        } catch (Throwable) {
            return false;
        }
    }

    public static function allowed(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);

        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    public static function setWebhook(Event $event, ?string $url): void
    {
        $event->forceFill(['social_webhook' => $url ?: null])->save();
    }

    /** "hook.eu1.make.com/…3f9a" — enough to recognise it, not to use it. */
    public static function masked(Event $event): string
    {
        $url = (string) $event->social_webhook;
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        return $host.'/…'.substr($url, -4);
    }

    /**
     * Saves the image and calls the webhook.
     *
     * @return array{ok: bool, message: string, image_url?: string}
     */
    public static function publish(Event $event, UploadedFile $image, string $title, string $caption, string $kind, string $by): array
    {
        if (! self::configured($event) || ! self::allowed((string) $event->social_webhook)) {
            return ['ok' => false, 'message' => 'Set a webhook first.'];
        }

        $path = $image->storeAs('social/'.$event->id, now()->format('Ymd-His').'-'.Str::slug($kind).'-'.Str::random(8).'.png', 'public');
        $imageUrl = url(Storage::disk('public')->url($path));

        $payload = [
            'event' => ['name' => $event->display_name, 'slug' => $event->slug, 'site' => $event->source_site_url, 'app' => SocialKit::appUrl($event)],
            'kind' => $kind,
            'title' => $title,
            'caption' => $caption,
            'hashtags' => SocialKit::hashtags($event),
            'image_url' => $imageUrl,
            'sent_by' => $by,
            'sent_at' => now()->toIso8601String(),
        ];

        try {
            $response = Http::timeout(15)->acceptJson()->post((string) $event->social_webhook, $payload);
        } catch (Throwable $e) {
            Log::warning('Social publish failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'The webhook could not be reached.', 'image_url' => $imageUrl];
        }

        Log::info('Social post sent to webhook', ['event_id' => $event->id, 'kind' => $kind, 'status' => $response->status(), 'by' => $by]);

        return $response->successful()
            ? ['ok' => true, 'message' => 'Sent to your webhook.', 'image_url' => $imageUrl]
            : ['ok' => false, 'message' => 'The webhook answered '.$response->status().'.', 'image_url' => $imageUrl];
    }
}
