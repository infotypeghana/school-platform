<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function view(User $user, Teacher $teacher): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $teacher->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isSchoolAdmin();
    }

    public function update(User $user, Teacher $teacher): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $teacher->tenant_id;
    }

    public function delete(User $user, Teacher $teacher): bool
    {
        return $user->isSchoolAdmin() && $user->tenant_id === $teacher->tenant_id;
    }
}
