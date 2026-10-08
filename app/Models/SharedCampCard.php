<?php

namespace App\Models;

use App\Support\EventTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A Camp Card its owner chose to show on the attendee list ("Show my Camp
 * Card on the attendee list", Camp Card page). Holds only what their card
 * shows — role, company, city, interests, "ask me about" (each only if they
 * put it on the card) and the link its QR opens — never the rest of the form.
 * Like a discovery profile, it is changed or removed only with the owner
 * token kept on their phone.
 */
class SharedCampCard extends Model
{
    protected $fillable = [
        'share_id',
        'owner_token_hash',
        'event_id',
        'attendee_roster_id',
        'fields',
        'expires_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'owner_token_hash',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function rosterEntry(): BelongsTo
    {
        return $this->belongsTo(AttendeeRoster::class, 'attendee_roster_id');
    }

    /** Same window as discovery profiles (DiscoveryProfile::scopeAlive). */
    public function scopeAlive(Builder $query, Event $event): Builder
    {
        if (EventTime::retained($event)) {
            return $query;
        }

        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return array{share_id: string, owner_token: string, owner_token_hash: string} */
    public static function generateCredentials(): array
    {
        $ownerToken = Str::random(48);

        return [
            'share_id' => hash('sha256', Str::random(40).microtime()),
            'owner_token' => $ownerToken,
            'owner_token_hash' => hash('sha256', $ownerToken),
        ];
    }

    public function ownerTokenMatches(string $ownerToken): bool
    {
        return hash_equals($this->owner_token_hash, hash('sha256', $ownerToken));
    }

    /**
     * What the attendee list shows: the stored card fields as they are. The
     * name, photo and links stay the list entry's own.
     *
     * @return array<string, mixed>
     */
    public function publicCard(): array
    {
        return $this->fields ?? [];
    }
}
