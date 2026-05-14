<?php

namespace App\Policies;

use App\Models\Fee;
use App\Models\User;

class FeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function view(User $user, Fee $fee): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $fee->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function update(User $user, Fee $fee): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $fee->tenant_id;
    }

    public function delete(User $user, Fee $fee): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $fee->tenant_id;
    }
}
