<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An FCM registration token of the native app, one per install and event.
 */
class NativePushToken extends Model
{
    protected $fillable = [
        'event_id',
        'device_id',
        'platform',
        'token',
        'app_version',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
