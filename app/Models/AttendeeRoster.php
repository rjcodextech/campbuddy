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
    ];

    protected $casts = [
        'links' => 'array',
        'is_suppressed' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** The discovery profile whose owner picked this entry as themselves, if any. */
    public function discoveryProfile(): HasOne
    {
        return $this->hasOne(DiscoveryProfile::class, 'attendee_roster_id');
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
