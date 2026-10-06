<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessType;
use App\Models\CeoBusinessAssignment;
use App\Models\Module;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ModuleService;
use App\Services\SubscriptionManager;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RestaurantController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        $restaurants = Restaurant::with(['subscription.plan', 'businessType'])->withCount('users')->latest()->paginate(15);

        return view('super-admin.businesses.index', compact('restaurants'));
    }

    /**
     * Super admin "enters" a restaurant — from this point until they exit,
     * they're treated as that restaurant's own manager: /manager/* routes
     * become reachable, and every tenant-scoped model (menu, POS, cashbook,
     * staff, etc.) is scoped to this restaurant, exactly like a real
     * manager would experience.
     */
    public function enter(Restaurant $restaurant)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        Tenancy::enter($restaurant);

        return redirect()->route('manager.dashboard')
            ->with('success', "You're now managing {$restaurant->name}.");
    }

    public function exit()
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        Tenancy::exit();

        return redirect()->route('admin.restaurants.index')
            ->with('success', 'Back to the platform-wide view.');
    }

    public function create()
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        ModuleService::ensureDefaults();

        $businessTypes = BusinessType::where('is_active', true)->orderBy('sort_order')->get();
        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();
        $inactiveModules = Module::where('is_active', false)->orderBy('sort_order')->get();
        $subscriptionPlans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        $selectedTypeId = old('business_type_id') ?: ($businessTypes->first()?->id ?? null);
        $selectedModules = old('enabled_modules', []);
        $selectedPlanSlug = old('plan');
        $selectedTemplate = old('customer_template', 'default');
        $customerTemplates = Restaurant::getAvailableCustomerMenuTemplates();

        if (empty($selectedModules) && $selectedTypeId) {
            $businessType = BusinessType::find($selectedTypeId);
            $selectedModules = $businessType
                ? Module::whereIn('key', ModuleService::getDefaultModuleKeysForBusinessType($businessType))->pluck('id')->toArray()
                : [];
        }

        return view('super-admin.businesses.create', compact('businessTypes', 'modules', 'selectedModules', 'subscriptionPlans', 'selectedPlanSlug', 'selectedTemplate', 'customerTemplates'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        ModuleService::ensureDefaults();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:restaurants,slug',
            'business_type_id' => ['required', Rule::exists('business_types', 'id')->where(fn($query) => $query->where('is_active', true))],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:25',
            'address' => 'nullable|string|max:500',
            'custom_domain' => 'nullable|string|max:255|unique:restaurants,custom_domain',
            'domain' => 'nullable|string|max:255|unique:restaurants,domain',
            'plan' => ['nullable', 'string', Rule::exists('subscription_plans', 'slug')->where(fn ($query) => $query->where('is_active', true))],
            'status' => 'nullable|in:trial,active,suspended,cancelled',
            'show_on_homepage' => 'nullable|boolean',
            'storefront_enabled' => 'nullable|boolean',
            'storefront_module_override' => 'nullable|boolean',
            'homepage_sort_order' => 'nullable|integer|min:0',
            'logo_path' => 'nullable|image|max:2048',
            'customer_template' => ['nullable', Rule::in(array_keys(Restaurant::getAvailableCustomerMenuTemplates()))],
            'enabled_modules' => 'nullable|array',
            'enabled_modules.*' => 'integer|exists:modules,id',
            'db_connection' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== null && trim($value) !== '') {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $fail('The tenant database configuration must be valid JSON.');
                    }
                }
            }],
            'owner_name' => 'nullable|string|max:255',
            'owner_email' => 'nullable|email|max:255',
            'owner_phone' => 'nullable|string|max:25',
            'owner_password' => 'nullable|string|min:8',
        ]);

        $validated['slug'] = Str::slug($validated['slug']);

        if ($request->hasFile('logo_path')) {
            $validated['logo_path'] = $request->file('logo_path')->store('restaurant-logos', 'public');
        }

        $selectedModuleKeys = [];
        if ($request->filled('enabled_modules')) {
            $selectedModuleKeys = Module::whereIn('id', $request->input('enabled_modules'))->pluck('key')->toArray();
        }

        if (empty($selectedModuleKeys)) {
            $businessType = BusinessType::find($validated['business_type_id']);
            $selectedModuleKeys = $businessType
                ? ModuleService::getDefaultModuleKeysForBusinessType($businessType)
                : [];
        }

        $plan = filled($validated['plan'] ?? null)
            ? SubscriptionPlan::where('slug', $validated['plan'])->where('is_active', true)->first()
            : SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->first();
        $validated['plan'] = $plan?->slug ?? 'basic';
        if ($plan?->max_modules !== null && count($selectedModuleKeys) > (int) $plan->max_modules) {
            return back()
                ->withErrors(['enabled_modules' => "The selected business type requires {$this->countModuleKeys($selectedModuleKeys)} modules, but the {$plan->name} plan allows {$plan->max_modules}. Choose a plan with a higher module cap or select modules manually."])
                ->withInput();
        }

        $restaurantData = $validated;
        $restaurantData['show_on_homepage'] = $request->boolean('show_on_homepage');
        $restaurantData['storefront_enabled'] = $request->boolean('storefront_enabled');
        $restaurantData['storefront_module_override'] = $request->boolean('storefront_module_override');
        $restaurantData['homepage_sort_order'] = (int) ($validated['homepage_sort_order'] ?? 0);
        if (Schema::hasColumn('restaurants', 'enabled_modules')) {
            $restaurantData['enabled_modules'] = $selectedModuleKeys;
        } else {
            unset($restaurantData['enabled_modules']);
        }

        if (Schema::hasColumn('restaurants', 'customer_template')) {
            $restaurantData['customer_template'] = $validated['customer_template'] ?? 'default';
        }

        if (isset($restaurantData['db_connection'])) {
            $restaurantData['db_connection'] = json_decode($restaurantData['db_connection'], true) ?: null;
        }

        $restaurant = Restaurant::create($restaurantData);

        if ($restaurant->status === 'active') {
            $restaurant->forceFill(['activated_at' => now()])->save();
        }

        $this->syncSubscriptionPlan($restaurant, $restaurantData['plan'] ?? null, $restaurantData['status'] ?? 'trial');
        $tenantProvisioningFailed = false;
        try {
            $this->provisionTenantDatabase($restaurant);
        } catch (\Throwable $exception) {
            report($exception);
            $tenantProvisioningFailed = true;
        }

        $ownerCredentials = null;

        if ($request->filled('owner_email')) {
            $password = $request->input('owner_password') ?: Str::random(10);

            $owner = User::create([
                'name' => $request->input('owner_name') ?: $validated['name'] . ' Owner',
                'email' => $request->input('owner_email'),
                'phone' => $request->input('owner_phone'),
                'role' => 'admin',
                'restaurant_id' => $restaurant->id,
                'password' => Hash::make($password),
            ]);

            $ownerCredentials = [
                'email' => $owner->email,
                'password' => $password,
            ];
        }

        $message = 'Restaurant created successfully.';

        if ($ownerCredentials) {
            $message .= ' Owner login: ' . $ownerCredentials['email'] . ' / ' . $ownerCredentials['password'];
        }

        $redirect = redirect()->route('admin.restaurants.index')->with('success', $message);

        if ($tenantProvisioningFailed) {
            $redirect->with('error', "Business #{$restaurant->id} was saved, but its tenant database could not be created or migrated. Grant the configured database user CREATE DATABASE, or enter the hPanel-created tenant connection under Edit Business, then retry with: php artisan tenants:migrate {$restaurant->id}");
        }

        return $redirect;
    }

    public function edit(Restaurant $restaurant)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        ModuleService::ensureDefaults();

        $businessTypes = BusinessType::where('is_active', true)->orderBy('sort_order')->get();
        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();
        $inactiveModules = Module::whereIn('key', ModuleService::professionalHospitalOptionalModuleKeys())
            ->orderBy('sort_order')
            ->get();
        $subscriptionPlans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();

        $selectedModules = old('enabled_modules');
        if (empty($selectedModules)) {
            if ($restaurant->enabled_modules && is_array($restaurant->enabled_modules)) {
                $selectedModules = Module::whereIn('key', $restaurant->enabled_modules)->pluck('id')->toArray();
            } else {
                $selectedModules = $restaurant->businessType?->modules()->pluck('id')->toArray() ?? [];
            }
        }

        $selectedPlanSlug = old('plan', $restaurant->plan ?? $restaurant->subscription?->plan?->slug ?? null);
        $selectedTemplate = old('customer_template', $restaurant->customer_template ?? 'default');
        $customerTemplates = Restaurant::getAvailableCustomerMenuTemplates();

        return view('super-admin.businesses.edit', compact('restaurant', 'businessTypes', 'modules', 'inactiveModules', 'selectedModules', 'subscriptionPlans', 'selectedPlanSlug', 'selectedTemplate', 'customerTemplates'));
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:restaurants,slug,' . $restaurant->id,
            'business_type_id' => ['required', Rule::exists('business_types', 'id')->where(fn($query) => $query->where('is_active', true))],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:25',
            'address' => 'nullable|string|max:500',
            'custom_domain' => 'nullable|string|max:255|unique:restaurants,custom_domain,' . $restaurant->id,
            'domain' => 'nullable|string|max:255|unique:restaurants,domain,' . $restaurant->id,
            'plan' => ['nullable', 'string', Rule::exists('subscription_plans', 'slug')->where(fn ($query) => $query->where('is_active', true))],
            'status' => 'nullable|in:trial,active,suspended,cancelled',
            'show_on_homepage' => 'nullable|boolean',
            'storefront_enabled' => 'nullable|boolean',
            'storefront_module_override' => 'nullable|boolean',
            'homepage_sort_order' => 'nullable|integer|min:0',
            'logo_path' => 'nullable|image|max:2048',
            'customer_template' => ['nullable', Rule::in(array_keys(Restaurant::getAvailableCustomerMenuTemplates()))],
            'enabled_modules' => 'nullable|array',
            'enabled_modules.*' => 'integer|exists:modules,id',
            'db_connection' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== null && trim($value) !== '') {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $fail('The tenant database configuration must be valid JSON.');
                    }
                }
            }],
        ]);

        $validated['slug'] = Str::slug($validated['slug']);

        if ($request->hasFile('logo_path')) {
            if ($restaurant->logo_path) {
                Storage::disk('public')->delete($restaurant->logo_path);
            }
            $validated['logo_path'] = $request->file('logo_path')->store('restaurant-logos', 'public');
        }

        $updateData = $validated;
        $updateData['show_on_homepage'] = $request->boolean('show_on_homepage');
        $updateData['storefront_enabled'] = $request->boolean('storefront_enabled');
        $updateData['storefront_module_override'] = $request->boolean('storefront_module_override');
        $updateData['homepage_sort_order'] = (int) ($validated['homepage_sort_order'] ?? $restaurant->homepage_sort_order ?? 0);
        $businessTypeChanged = (int) $restaurant->business_type_id !== (int) $validated['business_type_id'];

        if (Schema::hasColumn('restaurants', 'enabled_modules')) {
            if ($businessTypeChanged && ! $request->has('enabled_modules')) {
                $businessType = BusinessType::find($validated['business_type_id']);
                $updateData['enabled_modules'] = $businessType
                    ? ModuleService::getDefaultModuleKeysForBusinessType($businessType)
                    : [];
            } elseif ($request->has('enabled_modules')) {
                $updateData['enabled_modules'] = Module::whereIn('id', $request->input('enabled_modules', []))->pluck('key')->toArray();
            } else {
                $updateData['enabled_modules'] = $restaurant->enabled_modules ?? ModuleService::getDefaultModuleKeysForBusinessType($restaurant->businessType) ?? [];
            }
        } else {
            unset($updateData['enabled_modules']);
        }

        $plan = filled($validated['plan'] ?? null)
            ? SubscriptionPlan::where('slug', $validated['plan'])->where('is_active', true)->first()
            : SubscriptionPlan::where('slug', $restaurant->plan)->where('is_active', true)->first();
        $plan ??= SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->first();
        $updateData['plan'] = $plan?->slug ?? $restaurant->plan ?? 'basic';
        if ($plan?->max_modules !== null && isset($updateData['enabled_modules']) && count($updateData['enabled_modules']) > (int) $plan->max_modules) {
            return back()
                ->withErrors(['enabled_modules' => "The selected business type requires {$this->countModuleKeys($updateData['enabled_modules'])} modules, but the {$plan->name} plan allows {$plan->max_modules}. Choose a plan with a higher module cap or select modules manually."])
                ->withInput();
        }

        if (Schema::hasColumn('restaurants', 'customer_template')) {
            $updateData['customer_template'] = $validated['customer_template'] ?? $restaurant->customer_template ?? 'default';
        }

        if (isset($updateData['db_connection'])) {
            $updateData['db_connection'] = json_decode($updateData['db_connection'], true) ?: null;
        }

        $previousStatus = $restaurant->status;
        $restaurant->update($updateData);
        if (($updateData['status'] ?? $previousStatus) === 'active' && $previousStatus !== 'active') {
            $restaurant->forceFill(['activated_at' => now()])->save();
        }
        $this->syncSubscriptionPlan($restaurant, $updateData['plan'] ?? null, $updateData['status'] ?? $restaurant->status);
        try {
            $this->provisionTenantDatabase($restaurant);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.restaurants.index')
                ->with('error', "Business #{$restaurant->id} was updated, but its tenant database could not be migrated. Grant the configured database user CREATE DATABASE, or verify the hPanel tenant connection, then retry with: php artisan tenants:migrate {$restaurant->id}");
        }

        return redirect()->route('admin.restaurants.index')->with('success', 'Restaurant updated successfully.');
    }

    public function managerAccess(Restaurant $restaurant)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        $modules = $restaurant->getEnabledModules();
        $managers = User::where('restaurant_id', $restaurant->id)
            ->where('role', 'manager')
            ->orderBy('name')
            ->get();
        $owners = User::where('restaurant_id', $restaurant->id)
            ->where('role', 'admin')
            ->orderBy('name')
            ->get();

        return view('super-admin.businesses.manager-access', compact('restaurant', 'modules', 'managers', 'owners'));
    }

    public function updateManagerAccess(Request $request, Restaurant $restaurant, User $manager)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);
        abort_unless($manager->restaurant_id === $restaurant->id && $manager->isManagerRole(), 404);

        $allowedKeys = $restaurant->getEnabledModules()->pluck('key')->all();
        $validated = $request->validate([
            'module_access' => 'nullable|array',
            'module_access.*' => ['string', Rule::in($allowedKeys)],
        ]);

        $manager->update([
            'module_access' => array_values($validated['module_access'] ?? []),
        ]);

        return redirect()->route('admin.restaurants.manager-access', $restaurant)
            ->with('success', "Access updated for {$manager->name}.");
    }

    public function ceoAccess(Restaurant $restaurant)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        $ceos = User::where('role', 'ceo')
            ->with(['ceoBusinessAssignments' => fn ($query) => $query->where('restaurant_id', $restaurant->id)])
            ->orderBy('name')
            ->get();

        return view('super-admin.businesses.ceo-access', compact('restaurant', 'ceos'));
    }

    public function assignCeo(Request $request, Restaurant $restaurant)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        $validated = $request->validate([
            'ceo_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'ceo'))],
            'name' => 'required_without:ceo_user_id|nullable|string|max:255',
            'phone' => 'required_without:ceo_user_id|nullable|string|max:30|unique:users,phone',
            'email' => 'nullable|email|max:255|unique:users,email',
            'password' => 'required_without:ceo_user_id|nullable|string|min:8',
            'access_level' => ['required', Rule::in(['executive', 'financial', 'operations'])],
            'access_scope' => ['required', Rule::in(['all_branches', 'selected_branches'])],
        ]);

        $ceo = ! empty($validated['ceo_user_id'])
            ? User::where('role', 'ceo')->findOrFail($validated['ceo_user_id'])
            : User::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => 'ceo',
                'is_active' => true,
            ]);

        CeoBusinessAssignment::updateOrCreate(
            ['user_id' => $ceo->id, 'restaurant_id' => $restaurant->id],
            [
                'access_level' => $validated['access_level'],
                'access_scope' => $validated['access_scope'],
                'is_active' => true,
            ]
        );

        return redirect()->route('admin.restaurants.ceo-access', $restaurant)
            ->with('success', "{$ceo->name} can now access {$restaurant->name} from the CEO dashboard.");
    }

    protected function provisionTenantDatabase(Restaurant $restaurant): void
    {
        if (! config('tenancy.auto_provision', true)) {
            return;
        }

        app(TenantProvisioner::class)->provision($restaurant);
    }

    protected function syncSubscriptionPlan(Restaurant $restaurant, ?string $planSlug, string $status): void
    {
        if (blank($planSlug)) {
            return;
        }

        $planModel = SubscriptionPlan::where('slug', $planSlug)->first();
        if (!$planModel) {
            return;
        }

        $subscription = $restaurant->subscription()->first();

        if ($subscription) {
            $subscription->update([
                'subscription_plan_id' => $planModel->id,
                'status' => $status === 'active' ? 'active' : 'trial',
            ]);

            if ($status === 'active') {
                SubscriptionManager::upgradeToPaidSubscription($subscription);
            }

            return;
        }

        $newSubscription = SubscriptionManager::createTrialSubscription($restaurant, $planModel);
        if ($status === 'active') {
            SubscriptionManager::upgradeToPaidSubscription($newSubscription);
        }
    }

    protected function countModuleKeys(array $moduleKeys): int
    {
        return count(array_values(array_unique(array_filter($moduleKeys))));
    }

    public function destroy(Restaurant $restaurant)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        $restaurant->delete();

        return redirect()->route('admin.restaurants.index')->with('success', 'Restaurant deleted successfully.');
    }
}
