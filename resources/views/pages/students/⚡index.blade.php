<?php

use App\Enums\StudentStatus;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Students')] class extends Component {
    public string $firstName = '';
    public string $lastName = '';
    public string $dateOfBirth = '';
    public ?string $classId = null;

    // Set while an existing student is being edited.
    public ?int $editingId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Student::class);
    }

    /**
     * Admins see every student. Teachers see only students in classes they teach.
     * The query itself is limited, so other students are never even loaded.
     */
    #[Computed]
    public function students()
    {
        $user = Auth::user();

        $query = Student::with('schoolClass')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (! $user->isAdmin()) {
            $query->whereIn('school_class_id', $user->classes()->pluck('school_classes.id'));
        }

        return $query->get();
    }

    #[Computed]
    public function classes()
    {
        return SchoolClass::orderByDesc('school_year')->orderBy('name')->get(['id', 'name', 'school_year']);
    }

    /**
     * Student records are managed by admins only. Checked first on every
     * action so a teacher gets the same 403 whatever ID they try.
     */
    protected function authorizeAdmin(): void
    {
        Gate::authorize('manage-users');
    }

    public function edit(int $studentId): void
    {
        $this->authorizeAdmin();

        $student = Student::findOrFail($studentId);

        Gate::authorize('update', $student);

        $this->editingId = $student->id;
        $this->firstName = $student->first_name;
        $this->lastName = $student->last_name;
        $this->dateOfBirth = $student->date_of_birth->format('Y-m-d');
        $this->classId = $student->school_class_id !== null ? (string) $student->school_class_id : null;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'dateOfBirth' => ['required', 'date', 'before:today', 'after:1990-01-01'],
            'classId' => ['nullable', 'integer', 'exists:school_classes,id'],
        ]);

        $classId = ($validated['classId'] ?? '') === '' ? null : (int) $validated['classId'];

        $data = [
            'first_name' => trim($validated['firstName']),
            'last_name' => trim($validated['lastName']),
            'date_of_birth' => $validated['dateOfBirth'],
            'school_class_id' => $classId,
        ];

        if ($this->editingId === null) {
            Gate::authorize('create', Student::class);

            $student = Student::create($data);

            // Date of birth is deliberately left out of the audit log.
            AuditLogger::record('created', $student, null, [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'school_class_id' => $classId,
            ]);

            Flux::toast(variant: 'success', text: __('Student added.'));
        } else {
            $student = Student::findOrFail($this->editingId);

            Gate::authorize('update', $student);

            $student->fill($data);
            $changes = $student->getDirty();

            if ($changes === []) {
                $this->resetForm();
                Flux::toast(text: __('No changes to save.'));

                return;
            }

            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $student->getOriginal($field);
            }
            $new = $changes;

            // Record that the date of birth changed, never the dates themselves.
            if (array_key_exists('date_of_birth', $new)) {
                $old['date_of_birth'] = '[changed]';
                $new['date_of_birth'] = '[changed]';
            }

            $student->save();

            AuditLogger::record('updated', $student, $old, $new);

            Flux::toast(variant: 'success', text: __('Student updated.'));
        }

        $this->resetForm();
        unset($this->students);
    }

    public function withdraw(int $studentId): void
    {
        $this->authorizeAdmin();

        $student = Student::findOrFail($studentId);

        Gate::authorize('withdraw', $student);

        if ($student->status === StudentStatus::Withdrawn) {
            Flux::toast(text: __('This student is already withdrawn.'));

            return;
        }

        $student->forceFill(['status' => StudentStatus::Withdrawn->value])->save();

        AuditLogger::record(
            'withdrawn',
            $student,
            ['status' => StudentStatus::Active->value],
            ['status' => StudentStatus::Withdrawn->value],
        );

        unset($this->students);

        Flux::toast(variant: 'success', text: __('Student withdrawn.'));
    }

    protected function resetForm(): void
    {
        $this->reset('firstName', 'lastName', 'dateOfBirth', 'classId', 'editingId');
        $this->resetValidation();
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Students') }}</flux:heading>
    <flux:text class="mt-2">
        @if (auth()->user()->isAdmin())
            {{ __('Add students, move them between classes, and withdraw them when they leave. Students are never deleted.') }}
        @else
            {{ __('Students in the classes you teach.') }}
        @endif
    </flux:text>

    @can('create', App\Models\Student::class)
        <form wire:submit="save" class="my-6 max-w-lg space-y-4">
            <flux:heading>{{ $editingId ? __('Edit student') : __('Add a student') }}</flux:heading>

            <flux:input wire:model="firstName" :label="__('First name')" type="text" required autocomplete="off" />
            <flux:input wire:model="lastName" :label="__('Last name')" type="text" required autocomplete="off" />
            <flux:input wire:model="dateOfBirth" :label="__('Date of birth')" type="date" required />

            <div>
                <label for="classId" class="mb-1 block text-sm font-medium">{{ __('Class') }}</label>
                <select
                    id="classId"
                    wire:model="classId"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <option value="">{{ __('No class yet') }}</option>
                    @foreach ($this->classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->school_year }})</option>
                    @endforeach
                </select>
                @error('classId') <flux:text class="mt-1 text-red-600">{{ $message }}</flux:text> @enderror
            </div>

            <div class="flex gap-2">
                <flux:button variant="primary" type="submit" data-test="save-student-button">
                    {{ $editingId ? __('Save changes') : __('Add student') }}
                </flux:button>

                @if ($editingId)
                    <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                @endif
            </div>
        </form>
    @endcan

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full text-sm text-left">
            <thead class="border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="py-2 pr-4 font-medium">{{ __('Student') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Class') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Status') }}</th>
                    <th class="py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->students as $student)
                    <tr wire:key="student-{{ $student->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="py-2 pr-4">{{ $student->first_name }} {{ $student->last_name }}</td>
                        <td class="py-2 pr-4">{{ $student->schoolClass?->name ?? __('No class') }}</td>
                        <td class="py-2 pr-4">
                            @if ($student->status === App\Enums\StudentStatus::Active)
                                <flux:badge color="green">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc">{{ __('Withdrawn') }}</flux:badge>
                            @endif
                        </td>
                        <td class="py-2 text-right">
                            @if (auth()->user()->isAdmin())
                                <flux:button size="sm" wire:click="edit({{ $student->id }})">{{ __('Edit') }}</flux:button>

                                @if ($student->status === App\Enums\StudentStatus::Active)
                                    <flux:button
                                        size="sm"
                                        wire:click="withdraw({{ $student->id }})"
                                        wire:confirm="{{ __('Withdraw this student? They will stay on record but no longer appear as active.') }}"
                                    >
                                        {{ __('Withdraw') }}
                                    </flux:button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-4">
                            <flux:text>{{ __('No students to show.') }}</flux:text>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
