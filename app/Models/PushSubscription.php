<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'device_id',
        'endpoint',
        'p256dh_key',
        'auth_key',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
