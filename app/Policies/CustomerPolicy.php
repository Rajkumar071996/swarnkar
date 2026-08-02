<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        // Identifiers feed the network-wide identity, so edits stay with owners.
        return $user->isOwner();
    }

    /**
     * Reading a score is open to all roles; the real gate is the customer's OTP
     * consent, which is enforced separately in the lookup flow.
     */
    public function viewScore(User $user, Customer $customer): bool
    {
        return true;
    }
}
