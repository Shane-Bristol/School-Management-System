<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // The table has created_at only
    const UPDATED_AT = null;

    // Only ever created by application code, never from request input
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Append-only: refuse updates and deletes at the model level
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Short label for the record this entry is about, e.g. "Student #7".
     */
    public function subjectLabel(): string
    {
        return class_basename($this->auditable_type).' #'.$this->auditable_id;
    }

    /**
     * Human-readable lines describing what changed, for display.
     * Values were already redacted when the entry was written.
     *
     * @return array<int, string>
     */
    public function summary(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $format = function ($value): string {
            if (is_array($value)) {
                return $value === [] ? '(none)' : implode(', ', $value);
            }

            if (is_bool($value)) {
                return $value ? 'yes' : 'no';
            }

            return $value === null ? '(none)' : (string) $value;
        };

        $lines = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $label = str_replace('_', ' ', (string) $key);

            if (array_key_exists($key, $old) && array_key_exists($key, $new)) {
                $lines[] = $label.': '.$format($old[$key]).' → '.$format($new[$key]);
            } else {
                $lines[] = $label.': '.$format($new[$key] ?? $old[$key]);
            }
        }

        return $lines;
    }
}
