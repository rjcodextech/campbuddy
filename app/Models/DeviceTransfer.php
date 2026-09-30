<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One request to move an attendee's CampBuddy to another device
 * (DeviceTransferController). The payload is sealed by the two devices
 * (ECDH + AES-GCM), so the server never sees what's inside.
 */
class DeviceTransfer extends Model
{
    /** How long a request waits for the old device, and the sealed data for the new one. */
    public const WAIT_MINUTES = 10;

    /** A finished request (no data left in it) is kept this long, so both devices can see how it ended. */
    public const KEEP_DONE_MINUTES = 60 * 24;

    /** The sealed data's largest accepted size, in base64url characters (~1.5 MB). */
    public const MAX_PAYLOAD = 2_000_000;

    protected $fillable = [
        'event_id',
        'discovery_profile_id',
        'transfer_id',
        'status',
        'requester_key',
        'requester_secret_hash',
        'requester_label',
        'sender_key',
        'sender_secret_hash',
        'iv',
        'payload',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'requester_secret_hash',
        'sender_secret_hash',
        'payload',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DiscoveryProfile::class, 'discovery_profile_id');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /** Drops every request whose time is up — sealed data included. */
    public static function prune(): void
    {
        static::where('expires_at', '<=', now())->delete();
    }

    /** @return array{0: string, 1: string} a random secret and its hash */
    public static function secret(): array
    {
        $secret = Str::random(48);

        return [$secret, hash('sha256', $secret)];
    }

    public static function secretMatches(?string $hash, ?string $secret): bool
    {
        return $hash !== null && $secret !== null && $secret !== '' && hash_equals($hash, hash('sha256', $secret));
    }

    /** A coarse, non-identifying name for the device asking, from its browser's user agent. */
    public static function labelFor(?string $userAgent): ?string
    {
        $ua = strtolower((string) $userAgent);

        return match (true) {
            str_contains($ua, 'iphone') => 'an iPhone',
            str_contains($ua, 'ipad') => 'an iPad',
            str_contains($ua, 'android') && str_contains($ua, 'mobile') => 'an Android phone',
            str_contains($ua, 'android') => 'an Android tablet',
            str_contains($ua, 'macintosh') => 'a Mac',
            str_contains($ua, 'windows') => 'a Windows computer',
            str_contains($ua, 'cros') => 'a Chromebook',
            str_contains($ua, 'linux') => 'a Linux computer',
            default => null,
        };
    }
}
