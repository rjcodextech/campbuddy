<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One short message between two discovery matches (see the migration and
 * DiscoveryWaveController for the three-message, take-turns rules).
 */
class DiscoveryMessage extends Model
{
    public const UPDATED_AT = null;

    /** Per person, per conversation. */
    public const MAX_PER_PERSON = 3;

    public const MAX_LENGTH = 140;

    protected $fillable = ['event_id', 'from_profile_id', 'to_profile_id', 'body'];
}
