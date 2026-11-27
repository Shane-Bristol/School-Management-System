<?php

use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

function attClass(string $name): SchoolClass
{
    return SchoolClass::create(['name' => $name, 'school_year' => '2026-2027']);
}

function attStudent(SchoolClass $class, string $last): Student
{
    return Student::create([
        'first_name' => 'Pat',
        'last_name' => $last,
        'date_of_birth' => '2015-05-01',
        'school_class_id' => $class->id,
    ])->refresh();
}

function attRecord(Student $student, User $recorder, string $date, string $status = 'present'): Attendance
{
    $record = new Attendance([
        'student_id' => $student->id,
        'attendance_date' => $date,
        'status' => $status,
    ]);
    $record->forceFill(['recorded_by' => $recorder->id])->save();

    return $record;
}

it('redirects guests away from the attendance page', function () {
    $this->get('/attendance')->assertRedirect();
});

it('signs out a disabled user who opens the attendance page', function () {
    $this->actingAs(User::factory()->teacher()->disabled()->create())
        ->get('/attendance')
        ->assertRedirect(route('login'));
});

it('offers a teacher only the classes they teach', function () {
    $teacher = User::factory()->teacher()->create();
    $mine = attClass('Grade 6A');
    attClass('Grade 7Z');
    $mine->teachers()->attach($teacher->id);

    $this->actingAs($teacher)
        ->get('/attendance')
        ->assertOk()
        ->assertSee('Grade 6A')
        ->assertDontSee('Grade 7Z');
});

it('tells a teacher with no classes that none are assigned', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get('/attendance')
        ->assertOk()
        ->assertSee('not assigned to any classes');
});

it('lets a teacher record attendance for their own class and audits it', function () {
    $teacher = User::factory()->teacher()->create();
    $class = attClass('Grade 6A');
    $class->teachers()->attach($teacher->id);
    $one = attStudent($class, 'Alphaston');
    $two = attStudent($class, 'Betaville');

    Livewire::actingAs($teacher)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->set("marks.{$one->id}", 'present')
        ->set("marks.{$two->id}", 'absent')
        ->call('save')
        ->assertHasNoErrors();

    $record = Attendance::where('student_id', $one->id)->first();

    expect($record->status)->toBe(AttendanceStatus::Present);
    expect($record->recorded_by)->toBe($teacher->id);
    expect(Attendance::where('student_id', $two->id)->first()->status)->toBe(AttendanceStatus::Absent);

    $log = AuditLog::where('action', 'created')->where('auditable_id', $record->id)->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($teacher->id);
});

it('gives a teacher a 403 for a class they do not teach', function () {
    $teacher = User::factory()->teacher()->create();
    $other = attClass('Grade 7Z');
    $student = attStudent($other, 'Zulumann');

    Livewire::actingAs($teacher)
        ->test('pages::attendance.index')
        ->set('classId', (string) $other->id)
        ->set("marks.{$student->id}", 'present')
        ->call('save')
        ->assertForbidden();

    expect(Attendance::count())->toBe(0);
});

it('ignores marks for students outside the chosen class', function () {
    $teacher = User::factory()->teacher()->create();
    $mine = attClass('Grade 6A');
    $other = attClass('Grade 7Z');
    $mine->teachers()->attach($teacher->id);
    $inside = attStudent($mine, 'Alphaston');
    $outside = attStudent($other, 'Zulumann');

    Livewire::actingAs($teacher)
        ->test('pages::attendance.index')
        ->set('classId', (string) $mine->id)
        ->set("marks.{$inside->id}", 'present')
        ->set("marks.{$outside->id}", 'present')
        ->call('save');

    expect(Attendance::where('student_id', $inside->id)->exists())->toBeTrue();
    expect(Attendance::where('student_id', $outside->id)->exists())->toBeFalse();
});

it('does not list or record withdrawn students', function () {
    $admin = User::factory()->admin()->create();
    $class = attClass('Grade 6A');
    $active = attStudent($class, 'Alphaston');
    $gone = attStudent($class, 'Withdrawnson');
    $gone->forceFill(['status' => StudentStatus::Withdrawn->value])->save();

    Livewire::actingAs($admin)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->assertSee('Alphaston')
        ->assertDontSee('Withdrawnson')
        ->set("marks.{$gone->id}", 'present')
        ->call('save');

    expect(Attendance::where('student_id', $gone->id)->exists())->toBeFalse();
    expect(Attendance::where('student_id', $active->id)->exists())->toBeFalse();
});

it('rejects an invalid status', function () {
    $admin = User::factory()->admin()->create();
    $class = attClass('Grade 6A');
    $student = attStudent($class, 'Alphaston');

    Livewire::actingAs($admin)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->set("marks.{$student->id}", 'banana')
        ->call('save')
        ->assertHasErrors();

    expect(Attendance::count())->toBe(0);
});

it('rejects a future date', function () {
    $admin = User::factory()->admin()->create();
    $class = attClass('Grade 6A');
    $student = attStudent($class, 'Alphaston');

    Livewire::actingAs($admin)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->set('date', now()->addDay()->format('Y-m-d'))
        ->set("marks.{$student->id}", 'present')
        ->call('save')
        ->assertHasErrors(['date']);

    expect(Attendance::count())->toBe(0);
});

it('corrects an existing record instead of duplicating it, and audits the change', function () {
    $teacher = User::factory()->teacher()->create();
    $class = attClass('Grade 6A');
    $class->teachers()->attach($teacher->id);
    $student = attStudent($class, 'Alphaston');

    $component = Livewire::actingAs($teacher)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->set("marks.{$student->id}", 'present')
        ->call('save')
        ->set("marks.{$student->id}", 'absent')
        ->call('save');

    expect(Attendance::where('student_id', $student->id)->count())->toBe(1);

    $record = Attendance::where('student_id', $student->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Absent);

    $log = AuditLog::where('action', 'updated')->where('auditable_id', $record->id)->first();

    expect($log)->not->toBeNull();
    expect($log->old_values['status'])->toBe('present');
    expect($log->new_values['status'])->toBe('absent');
});

it('writes no audit entry when a save changes nothing', function () {
    $admin = User::factory()->admin()->create();
    $class = attClass('Grade 6A');
    $student = attStudent($class, 'Alphaston');

    Livewire::actingAs($admin)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->set("marks.{$student->id}", 'present')
        ->call('save')
        ->call('save');

    expect(AuditLog::where('action', 'created')->count())->toBe(1);
    expect(AuditLog::where('action', 'updated')->count())->toBe(0);
});

it('keeps note text out of the audit log', function () {
    $admin = User::factory()->admin()->create();
    $class = attClass('Grade 6A');
    $student = attStudent($class, 'Alphaston');

    Livewire::actingAs($admin)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->set("marks.{$student->id}", 'absent')
        ->call('save')
        ->set("notes.{$student->id}", 'has a fever')
        ->call('save');

    $record = Attendance::where('student_id', $student->id)->first();
    expect($record->note)->toBe('has a fever');

    $log = AuditLog::where('action', 'updated')->where('auditable_id', $record->id)->first();

    expect($log->new_values['note'])->toBe('[changed]');
    expect(json_encode($log->new_values))->not->toContain('fever');
    expect(json_encode($log->old_values))->not->toContain('fever');
});

it('lets an admin record attendance for any class', function () {
    $admin = User::factory()->admin()->create();
    $class = attClass('Grade 7Z');
    $student = attStudent($class, 'Zulumann');

    Livewire::actingAs($admin)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->set("marks.{$student->id}", 'late')
        ->call('save')
        ->assertHasNoErrors();

    expect(Attendance::where('student_id', $student->id)->first()->status)->toBe(AttendanceStatus::Late);
});

it('loads attendance already recorded for the chosen class and date', function () {
    $admin = User::factory()->admin()->create();
    $class = attClass('Grade 6A');
    $student = attStudent($class, 'Alphaston');
    $yesterday = now()->subDay()->format('Y-m-d');

    attRecord($student, $admin, $yesterday, 'excused');

    Livewire::actingAs($admin)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->set('date', $yesterday)
        ->assertSet("marks.{$student->id}", 'excused');
});

it('marks everyone present without overwriting existing choices', function () {
    $admin = User::factory()->admin()->create();
    $class = attClass('Grade 6A');
    $one = attStudent($class, 'Alphaston');
    $two = attStudent($class, 'Betaville');

    Livewire::actingAs($admin)
        ->test('pages::attendance.index')
        ->set('classId', (string) $class->id)
        ->set("marks.{$one->id}", 'absent')
        ->call('markAllPresent')
        ->assertSet("marks.{$one->id}", 'absent')
        ->assertSet("marks.{$two->id}", 'present');
});
