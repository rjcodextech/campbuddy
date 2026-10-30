<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * At most 500 characters (the column), always valid UTF-8. A message cut by bytes in the middle of
     * "—", "·" or a Hindi session title was invalid text, which MySQL refuses — and the whole job failed.
     */
    protected function message(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_substr(mb_scrub($value, 'UTF-8'), 0, 500));
    }
}
