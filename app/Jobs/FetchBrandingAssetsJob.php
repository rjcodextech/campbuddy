<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\FetchLog;
use App\Services\BrandingAssetFetcher;
use App\Support\SvgGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    /**
     * @param  bool  $onlyMissing  Fill in only the assets the event doesn't
     *                             have yet, leaving an existing logo/favicon
     *                             (e.g. an admin's upload) untouched. The
     *                             admin's "Re-fetch branding" button passes
     *                             false: it deliberately replaces both.
     */
    public function __construct(public readonly Event $event, public readonly bool $onlyMissing = false) {}

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
            if ($this->wants('logo_path') && ($logoUrl = $fetcher->findLogoUrl())) {
                if ($this->downloadTo($logoUrl, 'logo')) {
                    $found[] = 'logo';
                }
            }

            if ($this->wants('favicon_path') && ($faviconUrl = $fetcher->findFaviconUrl())) {
                if ($this->downloadTo($faviconUrl, 'favicon')) {
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

    /** Whether this run should fetch the asset held in the given column. */
    private function wants(string $column): bool
    {
        return ! $this->onlyMissing || blank($this->event->{$column});
    }

    /**
     * Downloads one asset with the same "don't trust a third party"
     * posture as the REST ingestion job: size-capped, MIME-checked,
     * short timeout. Stores it and points the event at it; returns false
     * if the source didn't pass validation (nothing is changed then).
     */
    private function downloadTo(string $url, string $baseName): bool
    {
        try {
            $response = Http::timeout(10)->get($url);
        } catch (Throwable) {
            // One unreachable asset host shouldn't stop the other asset from being fetched.
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $contentType = $response->header('Content-Type', '');
        $isImage = collect(self::ALLOWED_MIME_PREFIXES)->contains(fn ($prefix) => str_starts_with($contentType, $prefix));

        if (! $isImage || strlen($response->body()) > self::MAX_BYTES) {
            return false;
        }

        $extension = match (true) {
            str_contains($contentType, 'svg') => 'svg',
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'jpeg') => 'jpg',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'x-icon') || str_contains($contentType, 'vnd.microsoft.icon') => 'ico',
            default => 'png',
        };

        if ($extension === 'svg' && ! SvgGuard::isSafe($response->body())) {
            return false;
        }

        $this->event->storeBranding($baseName, $extension, $response->body());

        return true;
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
