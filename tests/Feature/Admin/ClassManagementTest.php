<?php

use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\User;
use Livewire\Livewire;

it('forbids teachers from the classes page', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get('/admin/classes')
        ->assertForbidden();
});

it('forbids a teacher from using the classes component directly', function () {
    Livewire::actingAs(User::factory()->teacher()->create())
        ->test('pages::admin.classes')
        ->assertForbidden();
});

it('lets an admin open the classes page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/classes')
        ->assertOk();
});

it('lets an admin create a class and audits it', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.classes')
        ->set('name', 'Grade 6A')
        ->set('schoolYear', '2026-2027')
        ->call('createClass')
        ->assertHasNoErrors();

    $class = SchoolClass::where('name', 'Grade 6A')->first();

    expect($class)->not->toBeNull();
    expect($class->school_year)->toBe('2026-2027');

    $log = AuditLog::where('action', 'created')->where('auditable_id', $class->id)->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($admin->id);
});

it('rejects badly formatted or non-consecutive school years', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.classes')
        ->set('name', 'Grade 6A')
        ->set('schoolYear', '2026')
        ->call('createClass')
        ->assertHasErrors(['schoolYear']);

    Livewire::actingAs($admin)
        ->test('pages::admin.classes')
        ->set('name', 'Grade 6A')
        ->set('schoolYear', '2026-2030')
        ->call('createClass')
        ->assertHasErrors(['schoolYear']);

    expect(SchoolClass::count())->toBe(0);
});

it('rejects a duplicate class name in the same school year', function () {
    $admin = User::factory()->admin()->create();
    SchoolClass::create(['name' => 'Grade 6A', 'school_year' => '2026-2027']);

    Livewire::actingAs($admin)
        ->test('pages::admin.classes')
        ->set('name', 'Grade 6A')
        ->set('schoolYear', '2026-2027')
        ->call('createClass')
        ->assertHasErrors(['name']);
});

it('lets an admin assign teachers and audits the change', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->teacher()->create();
    $class = SchoolClass::create(['name' => 'Grade 6A', 'school_year' => '2026-2027']);

    Livewire::actingAs($admin)
        ->test('pages::admin.classes')
        ->call('manageTeachers', $class->id)
        ->set('selectedTeachers', [(string) $teacher->id])
        ->call('saveTeachers');

    expect($class->teachers()->pluck('users.id')->all())->toBe([$teacher->id]);

    $log = AuditLog::where('action', 'updated')->where('auditable_id', $class->id)->first();
    expect($log)->not->toBeNull();
    expect($log->old_values['teacher_ids'])->toBe([]);
    expect($log->new_values['teacher_ids'])->toBe([$teacher->id]);
});

it('refuses to assign admins or disabled teachers to a class', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $disabledTeacher = User::factory()->teacher()->disabled()->create();
    $class = SchoolClass::create(['name' => 'Grade 6A', 'school_year' => '2026-2027']);

    foreach ([$otherAdmin, $disabledTeacher] as $user) {
        Livewire::actingAs($admin)
            ->test('pages::admin.classes')
            ->call('manageTeachers', $class->id)
            ->set('selectedTeachers', [(string) $user->id])
            ->call('saveTeachers');
    }

    expect($class->teachers()->count())->toBe(0);
    expect(AuditLog::where('auditable_id', $class->id)->count())->toBe(0);
});

it('does not write an audit entry when the teachers did not change', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->teacher()->create();
    $class = SchoolClass::create(['name' => 'Grade 6A', 'school_year' => '2026-2027']);
    $class->teachers()->attach($teacher->id);

    Livewire::actingAs($admin)
        ->test('pages::admin.classes')
        ->call('manageTeachers', $class->id)
        ->call('saveTeachers');

    expect(AuditLog::where('auditable_id', $class->id)->count())->toBe(0);
});
