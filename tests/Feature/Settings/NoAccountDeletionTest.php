<?php

use App\Models\User;
use Livewire\Livewire;

// Staff accounts are disabled by an admin, never deleted by the user.

it('does not offer account deletion on the profile page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Delete account');
});

it('no longer has a delete-account component to call', function () {
    $user = User::factory()->create();

    expect(fn () => Livewire::actingAs($user)->test('pages::settings.delete-user-form'))
        ->toThrow(Exception::class);

    expect(User::find($user->id))->not->toBeNull();
});
