<?php

use App\Models\AuditLog;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function lockTestEntry(): AuditLog
{
    return AuditLog::create([
        'action' => 'created',
        'auditable_type' => Student::class,
        'auditable_id' => 1,
        'new_values' => ['first_name' => 'Alphaston'],
    ]);
}

it('lets new audit entries be added', function () {
    lockTestEntry();
    lockTestEntry();

    expect(DB::table('audit_logs')->count())->toBe(2);
});

it('makes the database refuse raw updates to audit entries', function () {
    $entry = lockTestEntry();

    expect(fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['action' => 'tampered']))
        ->toThrow(QueryException::class, 'append-only');

    expect(DB::table('audit_logs')->where('id', $entry->id)->value('action'))->toBe('created');
});

it('makes the database refuse raw deletes of audit entries', function () {
    $entry = lockTestEntry();

    expect(fn () => DB::table('audit_logs')->where('id', $entry->id)->delete())
        ->toThrow(QueryException::class, 'append-only');

    expect(DB::table('audit_logs')->where('id', $entry->id)->exists())->toBeTrue();
});
