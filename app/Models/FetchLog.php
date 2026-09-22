<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FetchLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'fetch_log';

    protected $fillable = [
        'event_id',
        'source',
        'job_type',
        'status',
        'message',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
