<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report on a discovery profile, made from the report button on a match.
 * Status: open, removed (the profile was taken down) or dismissed.
 */
class DiscoveryReport extends Model
{
    public const REASONS = [
        'spam' => 'Spam or selling',
        'abusive' => 'Rude or abusive',
        'impersonation' => 'Pretending to be someone',
        'other' => 'Something else',
    ];

    protected $fillable = [
        'event_id',
        'discovery_id',
        'reporter_device_id',
        'reason',
        'note',
        'snapshot',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
