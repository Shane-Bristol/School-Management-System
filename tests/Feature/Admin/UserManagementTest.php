<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function userWithRole(Role $role, bool $active = true): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => $role->value, 'is_active' => $active])->save();

    return $user->refresh();
}

it('redirects guests away from the staff page', function () {
    $this->get('/admin/users')->assertRedirect();
});

it('forbids teachers from the staff page', function () {
    $this->actingAs(userWithRole(Role::Teacher))
        ->get('/admin/users')
        ->assertForbidden();
});

it('lets an active admin open the staff page', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->get('/admin/users')
        ->assertOk();
});

it('forbids a teacher from using the component directly', function () {
    Livewire::actingAs(userWithRole(Role::Teacher))
        ->test('pages::admin.users')
        ->assertForbidden();
});

it('lets an admin create a teacher, audits it, and sends a setup link', function () {
    Notification::fake();
    $admin = userWithRole(Role::Admin);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->set('name', 'Jane Teacher')
        ->set('email', 'Jane.Teacher@Example.test')
        ->call('createTeacher')
        ->assertHasNoErrors();

    $teacher = User::where('email', 'jane.teacher@example.test')->first();

    expect($teacher)->not->toBeNull();
    expect($teacher->role)->toBe(Role::Teacher);
    expect($teacher->is_active)->toBeTrue();

    $log = AuditLog::where('action', 'created')->where('auditable_id', $teacher->id)->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($admin->id);
    expect($log->new_values)->not->toHaveKey('password');

    Notification::assertSentTo($teacher, ResetPassword::class);
});

it('rejects duplicate emails when creating a teacher', function () {
    $admin = userWithRole(Role::Admin);
    $existing = userWithRole(Role::Teacher);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->set('name', 'Copy Cat')
        ->set('email', $existing->email)
        ->call('createTeacher')
        ->assertHasErrors(['email']);
});

it('lets an admin disable a teacher, ends their sessions, and audits it', function () {
    $admin = userWithRole(Role::Admin);
    $teacher = userWithRole(Role::Teacher);

    DB::table('sessions')->insert([
        'id' => 'test-session-id',
        'user_id' => $teacher->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => time(),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleActive', $teacher->id);

    expect($teacher->refresh()->is_active)->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $teacher->id)->count())->toBe(0);
    expect(AuditLog::where('action', 'disabled')->where('auditable_id', $teacher->id)->exists())->toBeTrue();
});

it('does not let an admin disable their own account', function () {
    $admin = userWithRole(Role::Admin);

    Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleActive', $admin->id);

    expect($admin->refresh()->is_active)->toBeTrue();
    expect(AuditLog::where('auditable_id', $admin->id)->count())->toBe(0);
});

it('signs out a disabled user on their next request', function () {
    $disabled = userWithRole(Role::Teacher, active: false);

    $this->actingAs($disabled)
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
