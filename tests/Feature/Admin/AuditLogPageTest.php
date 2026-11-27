<?php

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

it('redirects guests away from the audit log', function () {
    $this->get('/admin/audit-log')->assertRedirect();
});

it('forbids teachers from the audit log', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get('/admin/audit-log')
        ->assertForbidden();
});

it('signs out a disabled admin who opens the audit log', function () {
    $this->actingAs(User::factory()->admin()->disabled()->create())
        ->get('/admin/audit-log')
        ->assertRedirect(route('login'));
});

it('shows an admin the log entries', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);

    AuditLog::create([
        'user_id' => $admin->id,
        'action' => 'created',
        'auditable_type' => Student::class,
        'auditable_id' => 7,
        'new_values' => ['first_name' => 'Alphaston'],
        'ip_address' => '203.0.113.9',
    ]);

    $this->actingAs($admin)
        ->get('/admin/audit-log')
        ->assertOk()
        ->assertSee('Ada Admin')
        ->assertSee('Student #7')
        ->assertSee('Alphaston')
        ->assertSee('203.0.113.9');
});

it('filters entries by action', function () {
    $admin = User::factory()->admin()->create();

    AuditLog::create([
        'action' => 'created', 'auditable_type' => Student::class, 'auditable_id' => 1,
        'new_values' => ['first_name' => 'Alphaston'],
    ]);
    AuditLog::create([
        'action' => 'withdrawn', 'auditable_type' => Student::class, 'auditable_id' => 2,
        'new_values' => ['first_name' => 'Zulumann'],
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.audit-log')
        ->assertSee('Alphaston')
        ->assertSee('Zulumann')
        ->set('action', 'withdrawn')
        ->assertSee('Zulumann')
        ->assertDontSee('Alphaston');
});

it('escapes anything stored in the log instead of rendering it', function () {
    $admin = User::factory()->admin()->create();

    AuditLog::create([
        'action' => 'created', 'auditable_type' => Student::class, 'auditable_id' => 1,
        'new_values' => ['first_name' => '<script>alert(1)</script>'],
    ]);

    $this->actingAs($admin)
        ->get('/admin/audit-log')
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('describes changes in plain words', function () {
    $log = new AuditLog([
        'old_values' => ['teacher_ids' => [], 'status' => 'active'],
        'new_values' => ['teacher_ids' => [3, 4], 'status' => 'withdrawn'],
    ]);

    expect($log->summary())->toBe([
        'teacher ids: (none) → 3, 4',
        'status: active → withdrawn',
    ]);
});

it('labels entries that have no user', function () {
    $admin = User::factory()->admin()->create();

    AuditLog::create([
        'action' => 'created', 'auditable_type' => Student::class, 'auditable_id' => 1,
    ]);

    $this->actingAs($admin)
        ->get('/admin/audit-log')
        ->assertOk()
        ->assertSee('System or removed user');
});
