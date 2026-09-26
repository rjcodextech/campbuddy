<?php

namespace App\Models;

use Database\Factories\EventManagerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Someone an admin lets edit a few sections of specific events (details,
 * event information, quests & checklist) — never an event's status, and
 * nothing outside the events they are assigned. Signs in through the
 * `manager` guard at /manager/login; has no access to /admin.
 */
class EventManager extends Authenticatable
{
    /** @use HasFactory<EventManagerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_event_manager');
    }
}
