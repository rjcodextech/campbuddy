<?php

namespace App\Policies;

use App\Models\Quest;
use App\Models\User;

class QuestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Quest $quest): bool
    {
        return true;
    }

    public function delete(User $user, Quest $quest): bool
    {
        return true;
    }
}
