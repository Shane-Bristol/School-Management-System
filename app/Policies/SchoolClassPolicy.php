<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function view(User $user, SchoolClass $class): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $user->classes()->whereKey($class->getKey())->exists());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $user->isAdmin();
    }

    public function assignTeachers(User $user, SchoolClass $class): bool
    {
        return $user->isAdmin();
    }

    /**
     * Not allowed in version 1; classes with history should not disappear.
     */
    public function delete(User $user, SchoolClass $class): bool
    {
        return false;
    }
}
