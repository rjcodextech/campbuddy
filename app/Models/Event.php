<?php

namespace App\Models;

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
     * Never hotlinked — null falls back to CampBuddy's own
     * default branding at the view layer, not a broken image.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? Storage::disk('public')->url($this->favicon_path) : null;
    }
}
