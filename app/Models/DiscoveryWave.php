<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attendee waving at a discovery match (see the migration). Private:
 * never part of a public card — only the two people involved ever see it.
 */
class DiscoveryWave extends Model
{
    protected $fillable = ['event_id', 'from_profile_id', 'to_profile_id', 'reveal_name', 'message'];

    public function from(): BelongsTo
    {
        return $this->belongsTo(DiscoveryProfile::class, 'from_profile_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(DiscoveryProfile::class, 'to_profile_id');
    }
}
