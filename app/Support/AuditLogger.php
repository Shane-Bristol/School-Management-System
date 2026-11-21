<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Record who did what, to which record, from where.
     *
     * Callers pass only the fields worth recording. Never pass passwords,
     * tokens, or two-factor secrets in $old or $new.
     */
    public static function record(
        string $action,
        Model $subject,
        ?array $old = null,
        ?array $new = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
        ]);
    }
}
