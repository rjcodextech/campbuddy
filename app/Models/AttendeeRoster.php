<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
