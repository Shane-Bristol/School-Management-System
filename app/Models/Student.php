<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    // 'status' is deliberately not fillable: withdrawing a student is an explicit action.
    protected $fillable = ['first_name', 'last_name', 'date_of_birth', 'school_class_id'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'status' => StudentStatus::class,
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
