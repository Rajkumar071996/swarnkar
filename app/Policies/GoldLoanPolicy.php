<?php

namespace App\Policies;

use App\Models\GoldLoan;
use App\Models\User;

class GoldLoanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GoldLoan $loan): bool
    {
        return $user->store_id === $loan->store_id;
    }

    /**
     * Taking jewellery in against cash is the owner's call, the same way
     * extending store credit is.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function collect(User $user, GoldLoan $loan): bool
    {
        return $user->store_id === $loan->store_id;
    }

    public function release(User $user, GoldLoan $loan): bool
    {
        return $user->isOwner() && $user->store_id === $loan->store_id;
    }
}
