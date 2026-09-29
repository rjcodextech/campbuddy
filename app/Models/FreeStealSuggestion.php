<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Suggest a Free Steal" from Explore → Free Steals. Never shown to
 * attendees: an admin adds it as a Free Steal or dismisses it.
 */
class FreeStealSuggestion extends Model
{
    /** Past this many waiting, new ones are quietly dropped: the list can't be flooded. */
    public const MAX_WAITING = 300;

    protected $fillable = ['event_id', 'name', 'url', 'maker', 'why', 'email'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
