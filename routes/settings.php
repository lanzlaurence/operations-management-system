<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Personal settings
|--------------------------------------------------------------------------
|
| Everyone's own account: profile, password and appearance. These are Livewire
| screens that write through the authenticated user, so there are no separate
| update endpoints - the component owns both the form and the save.
|
| The account menu and Fortify's redirects both point at these route names, so
| they are worth leaving alone.
|
*/

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', Profile::class)->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/password', Password::class)->name('user-password.edit');

    Route::get('settings/appearance', Appearance::class)->name('appearance.edit');
});
