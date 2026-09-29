<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contact-form submission captured before an attendee opens a deal that
 * has Offer::capture_leads enabled — the fields that deal's form asks for
 * (DealForm): name, company, email, mobile and the products ticked.
 * Event-scoped (denormalized event_id alongside offer_id) so admin
 * filtering/export doesn't need to join through offers for every query —
 * and so a default deal's leads say which event they came from.
 */
class OfferLead extends Model
{
    protected $fillable = [
        'event_id',
        'offer_id',
        'name',
        'company',
        'email',
        'mobile',
        'choices',
    ];

    protected $casts = [
        'choices' => 'array',
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
