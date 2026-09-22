<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Offer $offer): bool
    {
        return true;
    }

    public function delete(User $user, Offer $offer): bool
    {
        return true;
    }
}
