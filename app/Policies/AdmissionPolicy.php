<?php

namespace App\Policies;

use App\Models\Admission;
use App\Models\User;

class AdmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function view(User $user, Admission $admission): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $admission->tenant_id;
    }

    public function update(User $user, Admission $admission): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $admission->tenant_id;
    }

    public function delete(User $user, Admission $admission): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $admission->tenant_id;
    }
}
