<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RequiredPasswordChangeController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('auth.required-password-change', [
            'isRequired' => $user->must_change_password,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'temporary_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:temporary_password', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        $destination = match ($user->role) {
            'super_admin' => 'admin.dashboard',
            'ceo' => 'manager.ceo.dashboard',
            default => 'manager.dashboard',
        };

        return redirect()->route($destination)
            ->with('success', 'Your password has been changed.');
    }
}
