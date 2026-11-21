<?php

use App\Models\User;

it('shows the staff accounts link to admins', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Staff accounts');
});

it('hides the staff accounts link from teachers', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Staff accounts');
});

it('does not show the starter kit repository links', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertDontSee('laravel/livewire-starter-kit');
});
