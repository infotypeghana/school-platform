<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /** Any authenticated school admin can list students. */
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function view(User $user, Student $student): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $student->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $student->tenant_id;
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $student->tenant_id;
    }
}
