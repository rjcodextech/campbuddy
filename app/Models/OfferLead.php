<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Name/Email/Mobile submission captured before an attendee opens a
 * deal that has Offer::capture_leads enabled. Event-scoped (denormalized
 * event_id alongside offer_id) so admin filtering/export doesn't need to
 * join through offers for every query.
 */
class OfferLead extends Model
{
    protected $fillable = [
        'event_id',
        'offer_id',
        'name',
        'email',
        'mobile',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
