<?php

namespace App\Policies;

use App\Models\Udhaar;
use App\Models\User;

class UdhaarPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Udhaar $udhaar): bool
    {
        return $user->store_id === $udhaar->store_id;
    }

    /**
     * Extending store credit is the owner's call. Staff record what has already
     * been agreed rather than granting new exposure at the counter.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function recordPayment(User $user, Udhaar $udhaar): bool
    {
        return $user->store_id === $udhaar->store_id;
    }

    public function writeOff(User $user, Udhaar $udhaar): bool
    {
        return $user->isOwner() && $user->store_id === $udhaar->store_id;
    }
}
