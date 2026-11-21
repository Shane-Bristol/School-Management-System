<?php

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;

// NOTE: these tests need a refreshed test database. If tests/Pest.php does not
// already apply RefreshDatabase to the Feature folder, add this line below:
// uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
| Role and is_active are not mass-assignable, so tests set them explicitly.
| Fresh models are refreshed so database defaults (e.g. student status) load.
*/

function makeUser(Role $role, bool $active = true): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => $role->value, 'is_active' => $active])->save();

    return $user->refresh();
}

function makeClass(string $name): SchoolClass
{
    return SchoolClass::create(['name' => $name, 'school_year' => '2026-2027'])->refresh();
}

function makeStudent(?SchoolClass $class, string $first = 'Test'): Student
{
    return Student::create([
        'first_name' => $first,
        'last_name' => 'Student',
        'date_of_birth' => '2015-05-01',
        'school_class_id' => $class?->id,
    ])->refresh();
}

function makeAttendance(Student $student, User $recorder): Attendance
{
    // recorded_by is not mass-assignable (the app sets it from the logged-in user),
    // and the column is NOT NULL, so it must be set before the first save.
    $record = new Attendance([
        'student_id' => $student->id,
        'attendance_date' => '2026-11-20',
        'status' => 'present',
    ]);
    $record->forceFill(['recorded_by' => $recorder->id])->save();

    return $record->refresh();
}

/*
|--------------------------------------------------------------------------
| Students
|--------------------------------------------------------------------------
*/

it('lets an admin view any student', function () {
    $admin = makeUser(Role::Admin);
    $student = makeStudent(makeClass('Grade 6A'));

    expect($admin->can('view', $student))->toBeTrue();
});

it('lets a teacher view students only in their own classes', function () {
    $teacher = makeUser(Role::Teacher);
    $own = makeClass('Grade 6A');
    $other = makeClass('Grade 6B');
    $own->teachers()->attach($teacher->id);

    expect($teacher->can('view', makeStudent($own, 'Mine')))->toBeTrue();
    expect($teacher->can('view', makeStudent($other, 'Theirs')))->toBeFalse();
});

it('does not let a teacher view a student with no class', function () {
    $teacher = makeUser(Role::Teacher);

    expect($teacher->can('view', makeStudent(null)))->toBeFalse();
});

it('lets only admins create, update, and withdraw students', function () {
    $admin = makeUser(Role::Admin);
    $teacher = makeUser(Role::Teacher);
    $class = makeClass('Grade 6A');
    $class->teachers()->attach($teacher->id);
    $student = makeStudent($class);

    expect($admin->can('create', Student::class))->toBeTrue();
    expect($admin->can('update', $student))->toBeTrue();
    expect($admin->can('withdraw', $student))->toBeTrue();

    expect($teacher->can('create', Student::class))->toBeFalse();
    expect($teacher->can('update', $student))->toBeFalse();
    expect($teacher->can('withdraw', $student))->toBeFalse();
});

it('never lets anyone delete a student', function () {
    $admin = makeUser(Role::Admin);
    $student = makeStudent(makeClass('Grade 6A'));

    expect($admin->can('delete', $student))->toBeFalse();
});

it('blocks disabled accounts from everything', function () {
    $disabledAdmin = makeUser(Role::Admin, active: false);
    $disabledTeacher = makeUser(Role::Teacher, active: false);
    $class = makeClass('Grade 6A');
    $class->teachers()->attach($disabledTeacher->id);
    $student = makeStudent($class);

    expect($disabledAdmin->can('view', $student))->toBeFalse();
    expect($disabledAdmin->can('create', Student::class))->toBeFalse();
    expect($disabledTeacher->can('view', $student))->toBeFalse();
    expect($disabledTeacher->can('record', [Attendance::class, $student]))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Attendance
|--------------------------------------------------------------------------
*/

it('lets a teacher record attendance only for active students in their classes', function () {
    $teacher = makeUser(Role::Teacher);
    $own = makeClass('Grade 6A');
    $other = makeClass('Grade 6B');
    $own->teachers()->attach($teacher->id);

    $mine = makeStudent($own, 'Mine');
    $theirs = makeStudent($other, 'Theirs');

    expect($teacher->can('record', [Attendance::class, $mine]))->toBeTrue();
    expect($teacher->can('record', [Attendance::class, $theirs]))->toBeFalse();
});

it('does not allow attendance to be recorded for withdrawn students', function () {
    $admin = makeUser(Role::Admin);
    $student = makeStudent(makeClass('Grade 6A'));
    $student->forceFill(['status' => StudentStatus::Withdrawn->value])->save();

    expect($admin->can('record', [Attendance::class, $student->refresh()]))->toBeFalse();
});

it('lets teachers view and correct attendance only in their own classes', function () {
    $teacher = makeUser(Role::Teacher);
    $own = makeClass('Grade 6A');
    $other = makeClass('Grade 6B');
    $own->teachers()->attach($teacher->id);

    $mine = makeStudent($own, 'Mine');
    $theirs = makeStudent($other, 'Theirs');

    $myRecord = makeAttendance($mine, $teacher);
    $theirRecord = makeAttendance($theirs, $teacher);

    expect($teacher->can('view', $myRecord))->toBeTrue();
    expect($teacher->can('update', $myRecord))->toBeTrue();
    expect($teacher->can('view', $theirRecord))->toBeFalse();
    expect($teacher->can('update', $theirRecord))->toBeFalse();
});

it('never lets anyone delete attendance history', function () {
    $admin = makeUser(Role::Admin);
    $student = makeStudent(makeClass('Grade 6A'));
    $record = makeAttendance($student, $admin);

    expect($admin->can('delete', $record))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Classes
|--------------------------------------------------------------------------
*/

it('lets teachers view only their own classes', function () {
    $teacher = makeUser(Role::Teacher);
    $own = makeClass('Grade 6A');
    $other = makeClass('Grade 6B');
    $own->teachers()->attach($teacher->id);

    expect($teacher->can('view', $own))->toBeTrue();
    expect($teacher->can('view', $other))->toBeFalse();
});

it('lets only admins manage classes and assign teachers', function () {
    $admin = makeUser(Role::Admin);
    $teacher = makeUser(Role::Teacher);
    $class = makeClass('Grade 6A');
    $class->teachers()->attach($teacher->id);

    expect($admin->can('create', SchoolClass::class))->toBeTrue();
    expect($admin->can('assignTeachers', $class))->toBeTrue();
    expect($teacher->can('create', SchoolClass::class))->toBeFalse();
    expect($teacher->can('update', $class))->toBeFalse();
    expect($teacher->can('assignTeachers', $class))->toBeFalse();
    expect($admin->can('delete', $class))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Audit log is append-only
|--------------------------------------------------------------------------
*/

it('refuses to update or delete audit log rows', function () {
    $log = AuditLog::create([
        'action' => 'created',
        'auditable_type' => Student::class,
        'auditable_id' => 1,
    ]);

    expect($log->update(['action' => 'tampered']))->toBeFalse();
    expect($log->delete())->toBeFalse();
    expect(AuditLog::find($log->id)->action)->toBe('created');
});

/*
|--------------------------------------------------------------------------
| Mass-assignment protection
|--------------------------------------------------------------------------
*/

it('does not let role or is_active be mass-assigned on users', function () {
    $user = new User;
    $user->fill(['name' => 'Eve', 'role' => 'admin', 'is_active' => false]);

    expect($user->role)->toBeNull();
    expect($user->is_active)->toBeNull();
});
