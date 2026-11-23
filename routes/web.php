<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Admin-only area. A teacher who types the URL gets a 403.
Route::middleware(['auth', 'verified', 'can:manage-users'])
    ->prefix('admin')
    ->group(function () {
        Route::livewire('users', 'pages::admin.users')->name('admin.users');
        Route::livewire('classes', 'pages::admin.classes')->name('admin.classes');
    });

require __DIR__.'/settings.php';
