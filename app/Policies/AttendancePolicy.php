<?php

namespace App\Policies;

use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;

class AttendancePolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $this->teachesStudent($user, $attendance->student);
    }

    /**
     * Record attendance for a student.
     * Use: Gate::authorize('record', [Attendance::class, $student]);
     */
    public function record(User $user, Student $student): bool
    {
        return $student->status === StudentStatus::Active
            && $this->teachesStudent($user, $student);
    }

    /**
     * Correct an existing record. Every correction is audit-logged elsewhere.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        return $this->teachesStudent($user, $attendance->student);
    }

    /**
     * Attendance history is never deleted.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return false;
    }

    private function teachesStudent(User $user, Student $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher()
            && $student->school_class_id !== null
            && $user->classes()->whereKey($student->school_class_id)->exists();
    }
}
