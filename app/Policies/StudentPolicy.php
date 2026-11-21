<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Runs before every check: a disabled account can do nothing.
     * Returning null lets the normal rules below decide.
     */
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    /**
     * Anyone active may open the student list, but the query must still be
     * limited to their classes (admins see all, teachers see their own).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher()
            && $student->school_class_id !== null
            && $user->classes()->whereKey($student->school_class_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function withdraw(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    /**
     * Students are withdrawn, never deleted.
     */
    public function delete(User $user, Student $student): bool
    {
        return false;
    }
}
