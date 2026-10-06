<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManagerAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->landingRedirect(Auth::user());
        }
        return view('manager.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
        ], [
            'phone.required' => 'Enter your manager phone number.',
            'password.required' => 'Enter your manager password.',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            Tenancy::end();

            $user = Auth::user();

            if (! in_array($user->role, ['super_admin', 'ceo', 'admin', 'manager', 'staff', 'cashier', 'kitchen', 'rider'], true)) {
                Auth::logout();
                return back()->withErrors(['phone' => 'This account cannot use the internal login.'])->onlyInput('phone');
            }

            if ($user->must_change_password) {
                return redirect()->route('password.required.edit');
            }

            if ($user->isSuperAdmin()) {
                $user->forceFill(['last_login_at' => now()])->save();

                return redirect()->intended(route('admin.dashboard'));
            }

            if ($user->isCeo()) {
                if (! $user->ceoBusinessAssignments()->where('is_active', true)->exists()) {
                    Auth::logout();
                    return back()->withErrors(['phone' => 'This CEO account has no active business access.'])->onlyInput('phone');
                }

                $user->forceFill(['last_login_at' => now()])->save();

                return redirect()->intended(route('manager.ceo.dashboard'));
            }

            if ($user->restaurant && $user->restaurant->status !== 'active') {
                Auth::logout();
                return back()->withErrors(['phone' => 'This restaurant account is currently inactive.'])->onlyInput('phone');
            }

            if ($user->restaurant && ($user->restaurant->restricted ?? false)) {
                Auth::logout();
                return back()->withErrors(['phone' => 'Manager logins for this restaurant have been restricted by the platform administrator.'])->onlyInput('phone');
            }

            $user->forceFill(['last_login_at' => now()])->save();

            return redirect()->intended($this->landingRedirect($user)->getTargetUrl());
        }

        return back()->withErrors(['credentials' => 'The phone number or password is incorrect.'])->withInput($request->only('phone', 'remember'));
    }

    public function logout(Request $request)
    {
        if (Auth::user()) {
            Auth::user()->forceFill(['last_logout_at' => now()])->save();
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('manager.login');
    }

    protected function landingRedirect($user)
    {
        if ($user->must_change_password) {
            return redirect()->route('password.required.edit');
        }

        return match ($user->role) {
            'super_admin' => redirect()->route('admin.dashboard'),
            'ceo' => redirect()->route('manager.ceo.dashboard'),
            default => redirect()->route('manager.dashboard'),
        };
    }
}
