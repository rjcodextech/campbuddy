<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Every authenticated user is currently an admin — there is no second
 * admin role yet (§21.3 notes this becomes a policy change, not a
 * rearchitect, when one is needed).
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return true;
    }

    /**
     * Hard delete is allowed only for draft events with nothing ingested
     * yet — anything else must be archived instead (§9 Events–Delete).
     */
    public function delete(User $user, Event $event): bool
    {
        return $event->status === 'draft';
    }
}
