<?php

namespace App\Models;

use App\Support\EventTime;
use App\Support\SafeUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DiscoveryProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'discovery_id',
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

    /**
     * Profiles that haven't expired. While the event is inside its retention
     * window (EventTime::retentionEnd) none has, whatever `expires_at` says:
     * profiles made before the event's real last day was known carry an
     * earlier stamp, and an attendee's profile must not vanish mid-event.
     * Afterwards the stored stamp decides.
     */
    public function scopeAlive(Builder $query, Event $event): Builder
    {
        if (EventTime::retained($event)) {
            return $query;
        }

        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** The attendee-list entry this profile's owner picked as themselves, if any. */
    public function rosterEntry(): BelongsTo
    {
        return $this->belongsTo(AttendeeRoster::class, 'attendee_roster_id');
    }

    /**
     * Everything other attendees may see, and nothing else: the fields the
     * owner chose to share, plus — only when they picked themselves from the
     * public attendee list — that entry's own public name, photo and links.
     * A typed-in name is shown as typed; an anonymous profile has no name.
     *
     * @return array<string, mixed>
     */
    public function publicCard(): array
    {
        $fields = $this->fields ?? [];
        $entry = $this->attendee_roster_id ? $this->rosterEntry : null;

        if ($entry?->is_suppressed) {
            $entry = null;
        }

        $wporg = $fields['wporg_username'] ?? null;

        return [
            'discovery_id' => $this->discovery_id,
            'name' => $entry?->name ?? ($fields['display_name'] ?? null),
            'on_attendee_list' => $entry !== null,
            'avatar_url' => SafeUrl::web($entry?->gravatar_url),
            'links' => collect($entry?->links ?? [])
                ->filter(fn ($link) => SafeUrl::web($link['url'] ?? null) !== null)
                ->values()
                ->all(),
            'wporg_url' => $wporg ? 'https://profiles.wordpress.org/'.rawurlencode($wporg).'/' : null,
            'fields' => [
                'tags' => $fields['tags'] ?? [],
                'profession' => $fields['profession'] ?? null,
                'who_to_meet' => $fields['who_to_meet'] ?? null,
            ],
        ];
    }

    /**
     * Generates the public discovery_id and the one-time-shown owner
     * token as a pair — only the token's hash is ever persisted.
     *
     * @return array{discovery_id: string, owner_token: string, owner_token_hash: string}
     */
    public static function generateCredentials(): array
    {
        $ownerToken = Str::random(48);

        return [
            'discovery_id' => hash('sha256', Str::random(40).microtime()),
            'owner_token' => $ownerToken,
            'owner_token_hash' => hash('sha256', $ownerToken),
        ];
    }

    public function ownerTokenMatches(string $ownerToken): bool
    {
        return hash_equals($this->owner_token_hash, hash('sha256', $ownerToken));
    }
}
