<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class UserCredentialController extends Controller
{
    private const INTERNAL_ROLES = ['super_admin', 'ceo', 'admin', 'manager', 'staff', 'cashier', 'kitchen', 'rider'];

    public function index(Request $request, TenantProvisioner $provisioner)
    {
        $search = trim((string) $request->query('q', ''));

        $businesses = Restaurant::query()
            ->with(['users' => function ($query): void {
                $query->whereIn('role', self::INTERNAL_ROLES)
                    ->where('id', '!=', Auth::id())
                    ->orderBy('name');
            }])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('users', function ($users) use ($search): void {
                            $users->whereIn('role', self::INTERNAL_ROLES)
                                ->where('id', '!=', Auth::id())
                                ->where(function ($users) use ($search): void {
                                    $users->where('name', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $businesses->getCollection()->each(function (Restaurant $business) use ($provisioner): void {
            $business->setAttribute('tenant_health', $provisioner->inspectDatabase($business));
        });

        return view('super-admin.users.index', [
            'businesses' => $businesses,
            'search' => $search,
        ]);
    }

    public function createManager(Request $request, Restaurant $restaurant, TenantProvisioner $provisioner)
    {
        $tenantHealth = $provisioner->inspectDatabase($restaurant);
        abort_unless($tenantHealth['ready'], 422, 'A manager login can only be added after this business tenant database is connected, fully migrated, and initialized.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique(User::class, 'phone')],
            'email' => ['nullable', 'email', 'max:150', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $manager = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        try {
            $provisioner->syncManagerAccount($restaurant, $manager);
        } catch (Throwable $exception) {
            report($exception);
            $manager->delete();

            return redirect()->route('admin.users.index')
                ->with('error', "The manager account was not created because it could not be added to business #{$restaurant->id}'s tenant database. Check its user-table schema and database grants, then retry.");
        }

        Log::notice('Super Admin created a business manager account', [
            'actor_id' => Auth::id(),
            'target_user_id' => $manager->id,
            'restaurant_id' => $restaurant->id,
        ]);

        return redirect()->route('admin.users.index')
            ->with('temporary_password', [
                'user_name' => $manager->name,
                'user_phone' => $manager->phone,
                'password' => $validated['password'],
            ]);
    }

    public function resetPassword(Request $request, User $user)
    {
        abort_unless(in_array($user->role, self::INTERNAL_ROLES, true), 404);
        abort_if($user->is(Auth::user()), 403, 'Use My Account to change your own password.');

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
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
                'password' => $validated['password'],
            ]);
    }

    public function updateUser(Request $request, User $user, TenantProvisioner $provisioner)
    {
        abort_unless(in_array($user->role, self::INTERNAL_ROLES, true), 404);
        abort_if($user->is(Auth::user()), 403, 'Use My Account to change your own details.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', Rule::unique(User::class, 'phone')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique(User::class, 'email')->ignore($user->id)],
        ]);

        $user->fill($validated)->save();

        $tenantSyncFailed = false;
        if ($user->restaurant_id) {
            $business = Restaurant::find($user->restaurant_id);
            if ($business && $provisioner->inspectDatabase($business)['ready']) {
                try {
                    $provisioner->syncManagerAccount($business, $user);
                } catch (Throwable $exception) {
                    report($exception);
                    $tenantSyncFailed = true;
                }
            }
        }

        Log::notice('Super Admin updated internal user details', [
            'actor_id' => Auth::id(),
            'target_user_id' => $user->id,
            'target_role' => $user->role,
            'restaurant_id' => $user->restaurant_id,
            'tenant_mirror_sync_failed' => $tenantSyncFailed,
        ]);

        $redirect = redirect()->route('admin.users.index')
            ->with('success', "Account details updated for {$user->name}.");

        if ($tenantSyncFailed) {
            $redirect->with('error', 'The central login details were saved, but the tenant user mirror could not be updated. Check the tenant database before relying on tenant-side user references.');
        }

        return $redirect;
    }
}
