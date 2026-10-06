<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserCredentialController extends Controller
{
    private const INTERNAL_ROLES = ['super_admin', 'ceo', 'admin', 'manager', 'staff', 'cashier', 'kitchen', 'rider'];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');

        $users = User::query()
            ->with('restaurant:id,name')
            ->whereIn('role', self::INTERNAL_ROLES)
            ->where('id', '!=', Auth::id())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($role, self::INTERNAL_ROLES, true), fn ($query) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('super-admin.users.index', [
            'users' => $users,
            'search' => $search,
            'selectedRole' => $role,
            'roles' => self::INTERNAL_ROLES,
        ]);
    }

    public function resetPassword(User $user)
    {
        abort_unless(in_array($user->role, self::INTERNAL_ROLES, true), 404);
        abort_if($user->is(Auth::user()), 403, 'Use My Account to change your own password.');

        $temporaryPassword = Str::random(32);
        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        Log::notice('Super Admin reset an internal user password', [
            'actor_id' => Auth::id(),
            'target_user_id' => $user->id,
            'target_role' => $user->role,
            'restaurant_id' => $user->restaurant_id,
        ]);

        return redirect()->route('admin.users.index')
            ->with('temporary_password', [
                'user_name' => $user->name,
                'user_phone' => $user->phone,
                'password' => $temporaryPassword,
            ]);
    }
}
