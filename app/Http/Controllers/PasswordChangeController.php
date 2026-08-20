<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordChangeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('auth/change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised(),
            ],
        ]);

        Auth::user()->update([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
            'force_password_change' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Password changed successfully');
    }
}
