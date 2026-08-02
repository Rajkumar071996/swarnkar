<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manageStaff(User $user): bool
    {
        return $user->isOwner();
    }

    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isOwner() && $user->store_id === $target->store_id;
    }

    public function delete(User $user, User $target): bool
    {
        // Owners may remove staff but not themselves, which would orphan the store.
        return $user->isOwner()
            && $user->store_id === $target->store_id
            && $user->id !== $target->id;
    }
}
