<?php

use App\Enums\StudentStatus;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

function scrClass(string $name): SchoolClass
{
    return SchoolClass::create(['name' => $name, 'school_year' => '2026-2027']);
}

function scrStudent(?SchoolClass $class, string $first, string $last): Student
{
    return Student::create([
        'first_name' => $first,
        'last_name' => $last,
        'date_of_birth' => '2015-05-01',
        'school_class_id' => $class?->id,
    ])->refresh();
}

it('redirects guests away from the students page', function () {
    $this->get('/students')->assertRedirect();
});

it('shows a teacher only the students in their own classes', function () {
    $teacher = User::factory()->teacher()->create();
    $mine = scrClass('Grade 6A');
    $other = scrClass('Grade 6B');
    $mine->teachers()->attach($teacher->id);

    scrStudent($mine, 'Pat', 'Alphaston');
    scrStudent($other, 'Sam', 'Zulumann');
    scrStudent(null, 'Lee', 'Noclassy');

    $this->actingAs($teacher)
        ->get('/students')
        ->assertOk()
        ->assertSee('Alphaston')
        ->assertDontSee('Zulumann')
        ->assertDontSee('Noclassy');
});

it('shows a teacher with no classes no students', function () {
    $teacher = User::factory()->teacher()->create();
    scrStudent(scrClass('Grade 6A'), 'Pat', 'Alphaston');

    $this->actingAs($teacher)
        ->get('/students')
        ->assertOk()
        ->assertDontSee('Alphaston');
});

it('shows an admin every student', function () {
    $admin = User::factory()->admin()->create();
    scrStudent(scrClass('Grade 6A'), 'Pat', 'Alphaston');
    scrStudent(scrClass('Grade 6B'), 'Sam', 'Zulumann');

    $this->actingAs($admin)
        ->get('/students')
        ->assertOk()
        ->assertSee('Alphaston')
        ->assertSee('Zulumann');
});

it('signs out a disabled user who opens the students page', function () {
    $this->actingAs(User::factory()->teacher()->disabled()->create())
        ->get('/students')
        ->assertRedirect(route('login'));
});

it('does not show teachers the student form', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get('/students')
        ->assertOk()
        ->assertDontSee('Add a student');
});

it('forbids teachers from adding, editing, or withdrawing students', function () {
    $teacher = User::factory()->teacher()->create();
    $class = scrClass('Grade 6A');
    $class->teachers()->attach($teacher->id);
    $student = scrStudent($class, 'Pat', 'Alphaston');

    Livewire::actingAs($teacher)
        ->test('pages::students.index')
        ->set('firstName', 'New')
        ->set('lastName', 'Kid')
        ->set('dateOfBirth', '2015-01-01')
        ->call('save')
        ->assertForbidden();

    Livewire::actingAs($teacher)
        ->test('pages::students.index')
        ->call('edit', $student->id)
        ->assertForbidden();

    Livewire::actingAs($teacher)
        ->test('pages::students.index')
        ->call('withdraw', $student->id)
        ->assertForbidden();

    expect(Student::count())->toBe(1);
    expect($student->refresh()->status)->toBe(StudentStatus::Active);
});

it('gives a teacher the same 403 for a student outside their classes', function () {
    $teacher = User::factory()->teacher()->create();
    $other = scrStudent(scrClass('Grade 6B'), 'Sam', 'Zulumann');

    Livewire::actingAs($teacher)
        ->test('pages::students.index')
        ->call('withdraw', $other->id)
        ->assertForbidden();

    Livewire::actingAs($teacher)
        ->test('pages::students.index')
        ->call('withdraw', 999999)
        ->assertForbidden();
});

it('lets an admin add a student and audits it without the date of birth', function () {
    $admin = User::factory()->admin()->create();
    $class = scrClass('Grade 6A');

    Livewire::actingAs($admin)
        ->test('pages::students.index')
        ->set('firstName', 'Pat')
        ->set('lastName', 'Alphaston')
        ->set('dateOfBirth', '2015-05-01')
        ->set('classId', (string) $class->id)
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::where('last_name', 'Alphaston')->first();

    expect($student)->not->toBeNull();
    expect($student->school_class_id)->toBe($class->id);

    $log = AuditLog::where('action', 'created')->where('auditable_id', $student->id)->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($admin->id);
    expect($log->new_values)->not->toHaveKey('date_of_birth');
    expect(json_encode($log->new_values))->not->toContain('2015');
});

it('rejects a future or missing date of birth', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::students.index')
        ->set('firstName', 'Pat')
        ->set('lastName', 'Alphaston')
        ->set('dateOfBirth', now()->addDay()->format('Y-m-d'))
        ->call('save')
        ->assertHasErrors(['dateOfBirth']);

    Livewire::actingAs($admin)
        ->test('pages::students.index')
        ->set('firstName', 'Pat')
        ->set('lastName', 'Alphaston')
        ->set('dateOfBirth', '')
        ->call('save')
        ->assertHasErrors(['dateOfBirth']);

    expect(Student::count())->toBe(0);
});

it('rejects a class that does not exist', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::students.index')
        ->set('firstName', 'Pat')
        ->set('lastName', 'Alphaston')
        ->set('dateOfBirth', '2015-05-01')
        ->set('classId', '999999')
        ->call('save')
        ->assertHasErrors(['classId']);
});

it('lets an admin move a student and audits the change', function () {
    $admin = User::factory()->admin()->create();
    $first = scrClass('Grade 6A');
    $second = scrClass('Grade 6B');
    $student = scrStudent($first, 'Pat', 'Alphaston');

    Livewire::actingAs($admin)
        ->test('pages::students.index')
        ->call('edit', $student->id)
        ->set('classId', (string) $second->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($student->refresh()->school_class_id)->toBe($second->id);

    $log = AuditLog::where('action', 'updated')->where('auditable_id', $student->id)->first();

    expect($log)->not->toBeNull();
    expect($log->old_values['school_class_id'])->toBe($first->id);
    expect($log->new_values['school_class_id'])->toBe($second->id);
});

it('logs that a date of birth changed without recording the dates', function () {
    $admin = User::factory()->admin()->create();
    $student = scrStudent(scrClass('Grade 6A'), 'Pat', 'Alphaston');

    Livewire::actingAs($admin)
        ->test('pages::students.index')
        ->call('edit', $student->id)
        ->set('dateOfBirth', '2014-03-03')
        ->call('save')
        ->assertHasNoErrors();

    $log = AuditLog::where('action', 'updated')->where('auditable_id', $student->id)->first();

    expect($log->new_values['date_of_birth'])->toBe('[changed]');
    expect(json_encode($log->old_values))->not->toContain('2015');
    expect(json_encode($log->new_values))->not->toContain('2014');
});

it('writes no audit entry when an edit changes nothing', function () {
    $admin = User::factory()->admin()->create();
    $student = scrStudent(scrClass('Grade 6A'), 'Pat', 'Alphaston');

    Livewire::actingAs($admin)
        ->test('pages::students.index')
        ->call('edit', $student->id)
        ->call('save');

    expect(AuditLog::where('auditable_id', $student->id)->count())->toBe(0);
});

it('lets an admin withdraw a student and audits it', function () {
    $admin = User::factory()->admin()->create();
    $student = scrStudent(scrClass('Grade 6A'), 'Pat', 'Alphaston');

    Livewire::actingAs($admin)
        ->test('pages::students.index')
        ->call('withdraw', $student->id);

    expect($student->refresh()->status)->toBe(StudentStatus::Withdrawn);

    $log = AuditLog::where('action', 'withdrawn')->where('auditable_id', $student->id)->first();

    expect($log)->not->toBeNull();
    expect($log->old_values['status'])->toBe('active');
    expect($log->new_values['status'])->toBe('withdrawn');
});
