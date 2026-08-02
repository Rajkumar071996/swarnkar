<?php

namespace App\Policies;

use App\Models\DefaultFlag;
use App\Models\User;

class DefaultFlagPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Reporting a default damages someone's standing across the whole network,
     * so it is restricted to owners and requires supporting evidence.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function withdraw(User $user, DefaultFlag $flag): bool
    {
        return $user->isOwner() && $user->store_id === $flag->store_id;
    }
}
