<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing an event manager changed (see App\Support\ManagerActivity). */
class EventManagerChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'event_manager_id',
        'manager_name',
        'event_id',
        'section',
        'action',
        'summary',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(EventManager::class, 'event_manager_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
