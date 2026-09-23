<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FetchLog;
use App\Services\BrandingAssetFetcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One-time branding asset discovery — dispatched
 * once when an event is approved, never on the daily schedule. Downloads
 * and re-hosts whatever it finds on CampBuddy's own storage so the
 * source site going down later can't break CampBuddy's branding.
 */
class FetchBrandingAssetsJob implements ShouldQueue
{
    use Queueable;

    /** Refuse anything larger — this is a logo/favicon, not a photo dump. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_MIME_PREFIXES = ['image/'];

    public int $tries = 2;

    public int $timeout = 45;

    public function __construct(public readonly Event $event) {}

    public function handle(): void
    {
        Cache::lock("ingest:branding:{$this->event->id}", 120)->block(10, function () {
            $this->fetchAndStore();
        });
    }

    private function fetchAndStore(): void
    {
        $fetcher = new BrandingAssetFetcher($this->event->source_site_url);
        $found = [];

        try {
            if ($logoUrl = $fetcher->findLogoUrl()) {
                if ($path = $this->downloadTo($logoUrl, 'logo')) {
                    $this->event->update(['logo_path' => $path]);
                    $found[] = 'logo';
                }
            }

            if ($faviconUrl = $fetcher->findFaviconUrl()) {
                if ($path = $this->downloadTo($faviconUrl, 'favicon')) {
                    $this->event->update(['favicon_path' => $path]);
                    $found[] = 'favicon';
                }
            }

            $this->log(
                'ok',
                $found === []
                    ? 'Nothing found automatically — admin upload is needed.'
                    : 'Fetched: '.implode(', ', $found)
            );
        } catch (Throwable $e) {
            $this->log('error', substr($e->getMessage(), 0, 500));

            throw $e;
        }
    }

    /**
     * Downloads one asset with the same "don't trust a third party"
     * posture as the REST ingestion job: size-capped, MIME-checked,
     * short timeout. Returns the stored path (relative to the public
     * disk) or null if the source didn't pass validation.
     */
    private function downloadTo(string $url, string $baseName): ?string
    {
        $response = Http::timeout(10)->get($url);

        if (! $response->successful()) {
            return null;
        }

        $contentType = $response->header('Content-Type', '');
        $isImage = collect(self::ALLOWED_MIME_PREFIXES)->contains(fn ($prefix) => str_starts_with($contentType, $prefix));

        if (! $isImage || strlen($response->body()) > self::MAX_BYTES) {
            return null;
        }

        $extension = match (true) {
            str_contains($contentType, 'svg') => 'svg',
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'jpeg') => 'jpg',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'x-icon') || str_contains($contentType, 'vnd.microsoft.icon') => 'ico',
            default => 'png',
        };

        $path = "branding/{$this->event->id}/{$baseName}.{$extension}";
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    private function log(string $status, string $message): void
    {
        FetchLog::create([
            'event_id' => $this->event->id,
            'source' => 'branding_assets',
            'job_type' => 'branding',
            'status' => $status,
            'message' => $message,
            'fetched_at' => now(),
        ]);
    }
}
