<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The store app's push token for a signed-in admin (role "admin", users.id)
 * or event manager (role "manager", event_managers.id).
 */
class OrganizerPushToken extends Model
{
    protected $fillable = ['role', 'user_id', 'platform', 'token', 'app_version'];
}
