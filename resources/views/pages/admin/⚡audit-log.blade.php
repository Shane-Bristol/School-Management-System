<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit log')] class extends Component {
    use WithPagination;

    public string $action = '';

    public function mount(): void
    {
        Gate::authorize('manage-users');
    }

    #[Computed]
    public function actions()
    {
        return AuditLog::query()->distinct()->orderBy('action')->pluck('action');
    }

    #[Computed]
    public function entries()
    {
        return AuditLog::with('user:id,name')
            ->when(
                $this->action !== '' && $this->actions->contains($this->action),
                fn ($query) => $query->where('action', $this->action),
            )
            ->orderByDesc('id')
            ->paginate(25);
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Audit log') }}</flux:heading>
    <flux:text class="mt-2">
        {{ __('A record of who changed what, and when. Entries cannot be edited or deleted.') }}
    </flux:text>

    <div class="my-6 max-w-xs">
        <label for="action" class="mb-1 block text-sm font-medium">{{ __('Filter by action') }}</label>
        <select
            id="action"
            wire:model.live="action"
            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
        >
            <option value="">{{ __('All actions') }}</option>
            @foreach ($this->actions as $name)
                <option value="{{ $name }}">{{ ucfirst($name) }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left">
            <thead class="border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="py-2 pr-4 font-medium">{{ __('When') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Who') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Action') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Record') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Details') }}</th>
                    <th class="py-2 font-medium">{{ __('From') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->entries as $entry)
                    <tr wire:key="log-{{ $entry->id }}" class="border-b border-zinc-100 align-top dark:border-zinc-800">
                        <td class="whitespace-nowrap py-2 pr-4">{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="py-2 pr-4">{{ $entry->user?->name ?? __('System or removed user') }}</td>
                        <td class="py-2 pr-4"><flux:badge>{{ ucfirst($entry->action) }}</flux:badge></td>
                        <td class="whitespace-nowrap py-2 pr-4">{{ $entry->subjectLabel() }}</td>
                        <td class="py-2 pr-4">
                            @foreach ($entry->summary() as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        </td>
                        <td class="whitespace-nowrap py-2">{{ $entry->ip_address }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-4">
                            <flux:text>{{ __('No entries yet.') }}</flux:text>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->entries->links() }}
    </div>
</section>
