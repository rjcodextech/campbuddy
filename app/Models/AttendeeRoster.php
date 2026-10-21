<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendeeRoster extends Model
{
    use HasFactory;

    protected $table = 'attendee_roster';

    protected $fillable = [
        'event_id',
        'name',
        'gravatar_url',
        'links',
        'content_hash',
        'is_suppressed',
        'is_microsponsor',
        'photo_url',
        'photo_source',
        'photo_checked_at',
    ];

    protected $casts = [
        'links' => 'array',
        'is_suppressed' => 'boolean',
        'is_microsponsor' => 'boolean',
        'photo_checked_at' => 'datetime',
    ];

    /** The picture to show: the best photo found for them (FillMissingPhotosJob), else what the attendee list gave. */
    public function photo(): ?string
    {
        return $this->photo_url ?: $this->gravatar_url;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** The discovery profile whose owner picked this entry as themselves, if any. */
    public function discoveryProfile(): HasOne
    {
        return $this->hasOne(DiscoveryProfile::class, 'attendee_roster_id');
    }

    /** The Camp Card this entry's owner chose to show on the attendee list, if any. */
    public function sharedCampCard(): HasOne
    {
        return $this->hasOne(SharedCampCard::class, 'attendee_roster_id');
    }

    /**
     * Hides this entry from the public list, and lets go of any discovery
     * profile that claimed it — a removed name mustn't live on in matches.
     */
    public function suppress(): void
    {
        $this->update(['is_suppressed' => true]);
        DiscoveryProfile::where('attendee_roster_id', $this->id)->update(['attendee_roster_id' => null]);
    }
}
