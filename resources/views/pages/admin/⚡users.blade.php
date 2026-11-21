<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Staff accounts')] class extends Component {
    public string $name = '';
    public string $email = '';

    public function mount(): void
    {
        Gate::authorize('manage-users');
    }

    #[Computed]
    public function staff()
    {
        return User::orderBy('name')->get();
    }

    /**
     * Create a teacher account. The teacher sets their own password through
     * an emailed reset link, so nobody ever types or shares one.
     */
    public function createTeacher(): void
    {
        // Every action re-checks permission: Livewire actions can be called
        // directly, so route middleware alone is not enough.
        Gate::authorize('manage-users');

        $this->email = Str::lower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // Unusable placeholder; the teacher replaces it via the emailed link.
            'password' => Str::random(40),
        ]);

        // role and is_active are not mass-assignable, so they are set explicitly.
        $user->forceFill([
            'role' => Role::Teacher->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        AuditLogger::record('created', $user, null, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => Role::Teacher->value,
        ]);

        Password::sendResetLink(['email' => $user->email]);

        $this->reset('name', 'email');
        unset($this->staff);

        Flux::toast(variant: 'success', text: __('Teacher account created. A password setup link was sent.'));
    }

    public function toggleActive(int $userId): void
    {
        Gate::authorize('manage-users');

        $user = User::findOrFail($userId);

        // Blocking self-disable also guarantees at least one active admin remains.
        if ($user->is(Auth::user())) {
            Flux::toast(variant: 'danger', text: __('You cannot disable your own account.'));

            return;
        }

        $old = ['is_active' => $user->is_active];

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        if (! $user->is_active) {
            // End any sessions the disabled user already has open.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        AuditLogger::record(
            $user->is_active ? 'enabled' : 'disabled',
            $user,
            $old,
            ['is_active' => $user->is_active],
        );

        unset($this->staff);

        Flux::toast(
            variant: 'success',
            text: $user->is_active ? __('Account enabled.') : __('Account disabled.'),
        );
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Staff accounts') }}</flux:heading>
    <flux:text class="mt-2">
        {{ __('Create teacher accounts and disable access when someone leaves. Accounts are never deleted.') }}
    </flux:text>

    <form wire:submit="createTeacher" class="my-6 max-w-lg space-y-4">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="off" />
        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" />

        <flux:button variant="primary" type="submit" data-test="create-teacher-button">
            {{ __('Create teacher account') }}
        </flux:button>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left">
            <thead class="border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="py-2 pr-4 font-medium">{{ __('Name') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Email') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Role') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('Status') }}</th>
                    <th class="py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->staff as $member)
                    <tr wire:key="staff-{{ $member->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="py-2 pr-4">{{ $member->name }}</td>
                        <td class="py-2 pr-4">{{ $member->email }}</td>
                        <td class="py-2 pr-4">
                            <flux:badge>{{ ucfirst($member->role?->value ?? 'unknown') }}</flux:badge>
                        </td>
                        <td class="py-2 pr-4">
                            @if ($member->is_active)
                                <flux:badge color="green">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="red">{{ __('Disabled') }}</flux:badge>
                            @endif
                        </td>
                        <td class="py-2 text-right">
                            @if ($member->id !== auth()->id())
                                @if ($member->is_active)
                                    <flux:button
                                        size="sm"
                                        wire:click="toggleActive({{ $member->id }})"
                                        wire:confirm="{{ __('Disable this account? They will be signed out.') }}"
                                    >
                                        {{ __('Disable') }}
                                    </flux:button>
                                @else
                                    <flux:button size="sm" wire:click="toggleActive({{ $member->id }})">
                                        {{ __('Enable') }}
                                    </flux:button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
