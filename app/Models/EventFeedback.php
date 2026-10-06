<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rating (1–5) and optional comment from the after-event thank-you card.
 * Anonymous: one per device per event, the device only as a hash.
 */
class EventFeedback extends Model
{
    protected $table = 'event_feedback';

    protected $fillable = ['event_id', 'device_hash', 'rating', 'comment'];

    protected $hidden = ['device_hash'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public static function deviceHash(Event $event, string $deviceId): string
    {
        return hash('sha256', $event->id.'|'.$deviceId);
    }
}
