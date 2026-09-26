<?php

namespace App\Policies;

use App\Models\EventManager;
use App\Models\User;

/**
 * Only admins (every signed-in `User`, as with the other policies) manage
 * event managers. An event manager is not a `User`, so it can never reach
 * these.
 */
class EventManagerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, EventManager $manager): bool
    {
        return true;
    }

    public function delete(User $user, EventManager $manager): bool
    {
        return true;
    }
}
