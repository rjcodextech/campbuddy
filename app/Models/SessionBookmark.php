<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionBookmark extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'device_id',
        'session_id',
        'reminder_enabled',
    ];

    protected $casts = [
        'reminder_enabled' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
