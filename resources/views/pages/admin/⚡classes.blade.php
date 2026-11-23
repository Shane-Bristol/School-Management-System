<?php

use App\Enums\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Classes')] class extends Component {
    public string $name = '';
    public string $schoolYear = '';

    // The class whose teachers are currently being edited, and the chosen teacher IDs.
    public ?int $managingId = null;
    public array $selectedTeachers = [];

    public function mount(): void
    {
        Gate::authorize('manage-users');
    }

    #[Computed]
    public function classes()
    {
        return SchoolClass::withCount('students')
            ->with('teachers:id,name')
            ->orderByDesc('school_year')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function teachers()
    {
        // Only active teachers can be assigned to classes.
        return User::where('role', Role::Teacher->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function createClass(): void
    {
        Gate::authorize('create', SchoolClass::class);

        $this->name = trim($this->name);
        $this->schoolYear = trim($this->schoolYear);

        $this->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('school_classes', 'name')->where('school_year', $this->schoolYear),
            ],
            'schoolYear' => ['required', 'regex:/^\d{4}-\d{4}$/'],
        ], [
            'name.unique' => __('That class already exists for this school year.'),
            'schoolYear.regex' => __('Use the format 2026-2027.'),
        ]);

        [$start, $end] = array_map('intval', explode('-', $this->schoolYear));

        if ($end !== $start + 1) {
            $this->addError('schoolYear', __('The second year must follow the first, e.g. 2026-2027.'));

            return;
        }

        $class = SchoolClass::create([
            'name' => $this->name,
            'school_year' => $this->schoolYear,
        ]);

        AuditLogger::record('created', $class, null, [
            'name' => $class->name,
            'school_year' => $class->school_year,
        ]);

        $this->reset('name', 'schoolYear');
        unset($this->classes);

        Flux::toast(variant: 'success', text: __('Class created.'));
    }

    public function manageTeachers(int $classId): void
    {
        $class = SchoolClass::findOrFail($classId);

        Gate::authorize('assignTeachers', $class);

        $this->managingId = $class->id;
        $this->selectedTeachers = $class->teachers()->pluck('users.id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function cancelManaging(): void
    {
        $this->reset('managingId', 'selectedTeachers');
    }

    public function saveTeachers(): void
    {
        abort_if($this->managingId === null, 422);

        $class = SchoolClass::findOrFail($this->managingId);

        Gate::authorize('assignTeachers', $class);

        $requested = collect($this->selectedTeachers)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // Reject anything that is not an active teacher, even if the request was tampered with.
        $valid = User::whereIn('id', $requested)
            ->where('role', Role::Teacher->value)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($valid->count() !== $requested->count()) {
            Flux::toast(variant: 'danger', text: __('Only active teachers can be assigned.'));

            return;
        }

        $before = $class->teachers()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $after = $valid->sort()->values()->all();

        $class->teachers()->sync($after);

        if ($before !== $after) {
            AuditLogger::record('updated', $class, ['teacher_ids' => $before], ['teacher_ids' => $after]);
        }

        $this->reset('managingId', 'selectedTeachers');
        unset($this->classes);

        Flux::toast(variant: 'success', text: __('Teachers updated.'));
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Classes') }}</flux:heading>
    <flux:text class="mt-2">
        {{ __('Create classes and choose which teachers can see their students.') }}
    </flux:text>

    <form wire:submit="createClass" class="my-6 max-w-lg space-y-4">
        <flux:input wire:model="name" :label="__('Class name')" type="text" required placeholder="Grade 6A" autocomplete="off" />
        <flux:input wire:model="schoolYear" :label="__('School year')" type="text" required placeholder="2026-2027" autocomplete="off" />

        <flux:button variant="primary" type="submit" data-test="create-class-button">
            {{ __('Create class') }}
        </flux:button>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left">
            <thead class="border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="py-2 pr-4 font-medium">{{ __('Class') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Year') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Teachers') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Students') }}</th>
                    <th class="py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->classes as $class)
                    <tr wire:key="class-{{ $class->id }}" class="border-b border-zinc-100 align-top dark:border-zinc-800">
                        <td class="py-2 pr-4">{{ $class->name }}</td>
                        <td class="py-2 pr-4">{{ $class->school_year }}</td>
                        <td class="py-2 pr-4">
                            {{ $class->teachers->pluck('name')->join(', ') ?: __('None assigned') }}
                        </td>
                        <td class="py-2 pr-4">{{ $class->students_count }}</td>
                        <td class="py-2 text-right">
                            <flux:button size="sm" wire:click="manageTeachers({{ $class->id }})">
                                {{ __('Assign teachers') }}
                            </flux:button>
                        </td>
                    </tr>

                    @if ($managingId === $class->id)
                        <tr wire:key="class-{{ $class->id }}-teachers">
                            <td colspan="5" class="bg-zinc-50 p-4 dark:bg-zinc-900">
                                <flux:heading>{{ __('Teachers for :class', ['class' => $class->name]) }}</flux:heading>

                                <div class="my-3 space-y-2">
                                    @forelse ($this->teachers as $teacher)
                                        <label class="flex items-center gap-2" wire:key="pick-{{ $class->id }}-{{ $teacher->id }}">
                                            <input type="checkbox" wire:model="selectedTeachers" value="{{ $teacher->id }}" class="rounded">
                                            <span>{{ $teacher->name }}</span>
                                        </label>
                                    @empty
                                        <flux:text>{{ __('There are no active teachers yet. Create one under Staff accounts.') }}</flux:text>
                                    @endforelse
                                </div>

                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="primary" wire:click="saveTeachers">{{ __('Save') }}</flux:button>
                                    <flux:button size="sm" wire:click="cancelManaging">{{ __('Cancel') }}</flux:button>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="py-4">
                            <flux:text>{{ __('No classes yet.') }}</flux:text>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
