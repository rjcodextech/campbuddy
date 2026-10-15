<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DPDPA 2023 Section 6 audit log: records affirmative, informed consent
 * collected from data principals when submitting contact forms or sharing data.
 */
class ConsentLog extends Model
{
    protected $fillable = [
        'event_id',
        'subject_type',
        'subject_identifier',
        'purpose',
        'notice_version',
        'ip_address',
        'user_agent',
        'consented_at',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
