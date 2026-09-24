<?php

namespace App\Models;

use App\Jobs\FetchBrandingAssetsJob;
use App\Jobs\FetchEventInfoJob;
use App\Jobs\FetchSpeakersSponsorsSessionsJob;
use App\Jobs\ParseAttendeeRosterJob;
use App\Observers\EventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(EventObserver::class)]
class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'display_name',
        'short_name',
        'source_site_url',
        'starts_on',
        'ends_on',
        'timezone',
        'timezone_locked',
        'logo_path',
        'favicon_path',
        'info',
        'info_fetched',
        'info_fetched_at',
        'status',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'timezone_locked' => 'boolean',
        'info' => 'array',
        'info_fetched' => 'array',
        'info_fetched_at' => 'datetime',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function attendeeRoster(): HasMany
    {
        return $this->hasMany(AttendeeRoster::class);
    }

    public function discoveryProfiles(): HasMany
    {
        return $this->hasMany(DiscoveryProfile::class);
    }

    public function sessionBookmarks(): HasMany
    {
        return $this->hasMany(SessionBookmark::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class);
    }

    /**
     * Gives this event its own editable copy of the default checklist
     * (Quest::DEFAULT_CHECKLIST). Matches on title, so calling it again
     * only fills in what's missing rather than duplicating.
     */
    public function seedDefaultChecklist(): void
    {
        $existing = $this->quests()->where('source', 'event')->pluck('title')->all();

        foreach (Quest::DEFAULT_CHECKLIST as $index => $title) {
            if (in_array($title, $existing, true)) {
                continue;
            }

            $this->quests()->create([
                'source' => 'event',
                'title' => $title,
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
            ]);
        }
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function fetchLogs(): HasMany
    {
        return $this->hasMany(FetchLog::class);
    }

    /**
     * Live = approved or active. Both mean "an admin has accepted this
     * event", and both need its branding, event information and (once active)
     * schedule data to exist.
     */
    public function isLive(): bool
    {
        return in_array($this->status, ['approved', 'active'], true);
    }

    /**
     * Queues everything a newly-live event needs so its pages aren't empty:
     * branding (only what's missing — never over an admin upload), event
     * information, and for an active event its schedule and attendee roster.
     *
     * Called whenever an event becomes live by ANY route — an admin approving
     * or activating it, creating it as active, or the lifecycle sweep
     * auto-publishing a draft — not only on the "approved" step, which the
     * sweep skips entirely.
     */
    public function queueInitialIngest(): void
    {
        if ($this->logo_path === null || $this->favicon_path === null) {
            FetchBrandingAssetsJob::dispatch($this, onlyMissing: true);
        }

        FetchEventInfoJob::dispatch($this);

        if ($this->status === 'active') {
            FetchSpeakersSponsorsSessionsJob::dispatch($this);

            if (! $this->attendeeRoster()->exists()) {
                ParseAttendeeRosterJob::dispatch($this);
            }
        }
    }

    /**
     * Never hotlinked — null falls back to CampBuddy's own
     * default branding at the view layer, not a broken image.
     */
    public function logoUrl(): ?string
    {
        return $this->brandingUrl($this->logo_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->brandingUrl($this->favicon_path);
    }

    /**
     * The best small, roughly-square mark for cards and avatars: the site icon
     * (favicon) if there is one, otherwise the logo, otherwise null.
     */
    public function markUrl(): ?string
    {
        return $this->faviconUrl() ?? $this->logoUrl();
    }

    /**
     * Saves a logo or favicon under branding/{id}/ and points the event at
     * it, deleting any earlier file of that kind (including one with a
     * different extension, e.g. logo.svg after a new logo.png).
     */
    public function storeBranding(string $kind, string $extension, string $contents): void
    {
        if (! in_array($kind, ['logo', 'favicon'], true)) {
            throw new \InvalidArgumentException("Unknown branding kind \"{$kind}\".");
        }

        $disk = Storage::disk('public');
        $directory = "branding/{$this->id}";
        $path = "{$directory}/{$kind}.{$extension}";

        $disk->put($path, $contents);

        foreach ($disk->files($directory) as $existing) {
            if ($existing !== $path && pathinfo($existing, PATHINFO_FILENAME) === $kind) {
                $disk->delete($existing);
            }
        }

        $this->update(["{$kind}_path" => $path]);
    }

    /**
     * Root-relative public URL with a version stamp. Branding files are
     * replaced in place (branding/1/logo.png stays the same path), so without
     * the stamp a re-fetched or re-uploaded logo would keep showing the old
     * one from browser, CDN and service-worker caches. The stamp is the file's
     * own modification time, so it only changes when the file does.
     */
    private function brandingUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        try {
            $version = (int) $disk->lastModified($path);
        } catch (\Throwable) {
            $version = 0;
        }

        return $disk->url($path).($version > 0 ? '?v='.$version : '');
    }
}
