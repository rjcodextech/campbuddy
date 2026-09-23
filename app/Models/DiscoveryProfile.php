<?php

namespace App\Models;

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
