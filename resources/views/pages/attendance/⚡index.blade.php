<?php

use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Attendance')] class extends Component {
    public ?string $classId = null;
    public string $date = '';

    // Keyed by student ID: the chosen status and an optional note.
    public array $marks = [];
    public array $notes = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', SchoolClass::class);

        $this->date = now()->format('Y-m-d');
    }

    /**
     * Admins can choose any class. Teachers can choose only classes they teach.
     */
    #[Computed]
    public function classes()
    {
        $user = Auth::user();

        $query = $user->isAdmin() ? SchoolClass::query() : $user->classes();

        return $query->orderByDesc('school_year')->orderBy('name')->get(['school_classes.id', 'school_classes.name', 'school_classes.school_year']);
    }

    /**
     * The chosen class, but only if this user is allowed to use it.
     */
    #[Computed]
    public function selectedClass(): ?SchoolClass
    {
        if ($this->classId === null || $this->classId === '') {
            return null;
        }

        return $this->classes->firstWhere('id', (int) $this->classId);
    }

    /**
     * Active students in the chosen class. Withdrawn students are not listed.
     */
    #[Computed]
    public function roster()
    {
        $class = $this->selectedClass;

        if (! $class) {
            return collect();
        }

        return Student::where('school_class_id', $class->id)
            ->where('status', StudentStatus::Active->value)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function updatedClassId(): void
    {
        $this->loadMarks();
    }

    public function updatedDate(): void
    {
        $this->loadMarks();
    }

    public function markAllPresent(): void
    {
        foreach ($this->roster as $student) {
            if (($this->marks[$student->id] ?? '') === '') {
                $this->marks[$student->id] = AttendanceStatus::Present->value;
            }
        }
    }

    /**
     * Fill the form from any attendance already recorded for this class and date.
     */
    protected function loadMarks(): void
    {
        $this->marks = [];
        $this->notes = [];

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
            return;
        }

        $ids = $this->roster->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $existing = Attendance::whereDate('attendance_date', $this->date)
            ->whereIn('student_id', $ids)
            ->get();

        foreach ($existing as $record) {
            $this->marks[$record->student_id] = $record->status->value;
            $this->notes[$record->student_id] = $record->note ?? '';
        }
    }

    public function save(): void
    {
        Gate::authorize('viewAny', SchoolClass::class);

        if ($this->classId === null || $this->classId === '') {
            $this->addError('classId', __('Choose a class first.'));

            return;
        }

        $class = $this->selectedClass;

        // A class this user may not use gets a 403, same as any other forbidden action.
        abort_if($class === null, 403);

        Gate::authorize('view', $class);

        $this->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:2020-01-01'],
            'marks' => ['array'],
            'marks.*' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'notes' => ['array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $created = 0;
        $corrected = 0;

        DB::transaction(function () use ($class, &$created, &$corrected) {
            $roster = $this->roster;

            $existing = Attendance::whereDate('attendance_date', $this->date)
                ->whereIn('student_id', $roster->pluck('id'))
                ->get()
                ->keyBy('student_id');

            // Only students on this class's roster are considered. Any other IDs
            // in the submitted data are ignored.
            foreach ($roster as $student) {
                $status = (string) ($this->marks[$student->id] ?? '');

                if ($status === '') {
                    continue;
                }

                $note = trim((string) ($this->notes[$student->id] ?? ''));
                $record = $existing->get($student->id);

                if ($record === null) {
                    Gate::authorize('record', [Attendance::class, $student]);

                    $record = new Attendance([
                        'student_id' => $student->id,
                        'attendance_date' => $this->date,
                        'status' => $status,
                        'note' => $note !== '' ? $note : null,
                    ]);
                    // recorded_by comes from the logged-in user, never from the form.
                    $record->forceFill(['recorded_by' => Auth::id()])->save();

                    AuditLogger::record('created', $record, null, [
                        'student_id' => $student->id,
                        'attendance_date' => $this->date,
                        'status' => $status,
                    ]);

                    $created++;

                    continue;
                }

                $statusChanged = $record->status->value !== $status;
                $noteChanged = (string) ($record->note ?? '') !== $note;

                if (! $statusChanged && ! $noteChanged) {
                    continue;
                }

                Gate::authorize('update', $record);

                $old = ['student_id' => $student->id, 'attendance_date' => $this->date];
                $new = ['student_id' => $student->id, 'attendance_date' => $this->date];

                if ($statusChanged) {
                    $old['status'] = $record->status->value;
                    $new['status'] = $status;
                }

                // Notes can contain private details, so the log records only that one changed.
                if ($noteChanged) {
                    $old['note'] = '[changed]';
                    $new['note'] = '[changed]';
                }

                $record->status = $status;
                $record->note = $note !== '' ? $note : null;
                $record->save();

                AuditLogger::record('updated', $record, $old, $new);

                $corrected++;
            }
        });

        $this->loadMarks();

        if ($created === 0 && $corrected === 0) {
            Flux::toast(text: __('Nothing to save.'));

            return;
        }

        Flux::toast(
            variant: 'success',
            text: __('Attendance saved: :created recorded, :corrected corrected.', [
                'created' => $created,
                'corrected' => $corrected,
            ]),
        );
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Attendance') }}</flux:heading>
    <flux:text class="mt-2">{{ __('Record attendance for one class and one day. Corrections are saved and logged.') }}</flux:text>

    @if ($this->classes->isEmpty())
        <flux:text class="mt-6">
            {{ __('You are not assigned to any classes yet. An administrator can assign you.') }}
        </flux:text>
    @else
        <div class="my-6 flex max-w-xl flex-wrap items-end gap-4">
            <div class="min-w-48 flex-1">
                <label for="classId" class="mb-1 block text-sm font-medium">{{ __('Class') }}</label>
                <select
                    id="classId"
                    wire:model.live="classId"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <option value="">{{ __('Choose a class') }}</option>
                    @foreach ($this->classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->school_year }})</option>
                    @endforeach
                </select>
                @error('classId') <flux:text class="mt-1 text-red-600">{{ $message }}</flux:text> @enderror
            </div>

            <div>
                <flux:input wire:model.live="date" :label="__('Date')" type="date" max="{{ now()->format('Y-m-d') }}" />
                @error('date') <flux:text class="mt-1 text-red-600">{{ $message }}</flux:text> @enderror
            </div>
        </div>

        @if ($this->selectedClass)
            @if ($this->roster->isEmpty())
                <flux:text>{{ __('There are no active students in this class.') }}</flux:text>
            @else
                <form wire:submit="save">
                    <div class="mb-3">
                        <flux:button type="button" size="sm" wire:click="markAllPresent">
                            {{ __('Mark everyone present') }}
                        </flux:button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-left">
                            <thead class="border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2 pr-4 font-medium">{{ __('Student') }}</th>
                                    @foreach (App\Enums\AttendanceStatus::cases() as $status)
                                        <th class="py-2 pr-4 font-medium">{{ ucfirst($status->value) }}</th>
                                    @endforeach
                                    <th class="py-2 font-medium">{{ __('Note') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->roster as $student)
                                    <tr wire:key="att-{{ $student->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                                        <td class="py-2 pr-4">{{ $student->first_name }} {{ $student->last_name }}</td>
                                        @foreach (App\Enums\AttendanceStatus::cases() as $status)
                                            <td class="py-2 pr-4">
                                                <input
                                                    type="radio"
                                                    name="mark-{{ $student->id }}"
                                                    value="{{ $status->value }}"
                                                    wire:model="marks.{{ $student->id }}"
                                                    aria-label="{{ $student->first_name }} {{ $student->last_name }}: {{ $status->value }}"
                                                >
                                            </td>
                                        @endforeach
                                        <td class="py-2">
                                            <input
                                                type="text"
                                                maxlength="255"
                                                wire:model="notes.{{ $student->id }}"
                                                class="w-full min-w-40 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                                aria-label="{{ __('Note for :name', ['name' => $student->first_name.' '.$student->last_name]) }}"
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @error('marks.*') <flux:text class="mt-2 text-red-600">{{ $message }}</flux:text> @enderror
                    @error('notes.*') <flux:text class="mt-2 text-red-600">{{ $message }}</flux:text> @enderror

                    <div class="mt-4">
                        <flux:button variant="primary" type="submit" data-test="save-attendance-button">
                            {{ __('Save attendance') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        @endif
    @endif
</section>
