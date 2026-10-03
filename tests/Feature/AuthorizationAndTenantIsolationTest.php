<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ModuleService;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationAndTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_cannot_access_manager_or_ceo_routes_without_impersonating(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'phone' => '03000000010',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('manager.dashboard'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('ceo.dashboard'))
            ->assertForbidden();
    }

    public function test_manager_cannot_access_super_admin_or_ceo_routes(): void
    {
        $restaurant = $this->createRestaurant(['enabled_modules' => ['menu']]);
        $manager = $this->createManager($restaurant);

        $this->actingAs($manager)
            ->get(route('admin.modules.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('ceo.dashboard'))
            ->assertForbidden();
    }

    public function test_ceo_cannot_access_super_admin_or_manager_routes(): void
    {
        $ceo = User::factory()->create([
            'role' => 'ceo',
            'is_active' => true,
            'phone' => '03000000011',
        ]);

        $this->actingAs($ceo)
            ->get(route('admin.modules.index'))
            ->assertForbidden();

        $this->actingAs($ceo)
            ->get(route('manager.dashboard'))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_staff_routes(): void
    {
        $customer = Customer::create([
            'name' => 'Customer',
            'phone' => '03000000001',
            'email' => 'customer@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('manager.dashboard'))
            ->assertForbidden();
    }

    public function test_manager_cannot_access_another_restaurants_menu_item_by_guessing_its_id(): void
    {
        $restaurantA = $this->createRestaurant(['enabled_modules' => ['menu']]);
        $restaurantB = $this->createRestaurant([
            'name' => 'Restaurant B',
            'slug' => 'restaurant-b',
            'enabled_modules' => ['menu'],
        ]);
        $categoryB = Category::create([
            'restaurant_id' => $restaurantB->id,
            'name' => 'Other Category',
            'slug' => 'other-category',
        ]);
        $itemB = MenuItem::create([
            'restaurant_id' => $restaurantB->id,
            'category_id' => $categoryB->id,
            'name' => 'Other Restaurant Item',
            'price' => 100,
            'is_available' => true,
        ]);
        $managerA = $this->createManager($restaurantA, ['menu']);

        $this->actingAs($managerA)
            ->get(route('manager.menu-items.edit', ['item' => $itemB]))
            ->assertNotFound();
    }

    public function test_super_admin_impersonation_switches_and_exit_restores_platform_context(): void
    {
        $restaurant = $this->createRestaurant(['enabled_modules' => ['menu']]);
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'phone' => '03000000012',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.restaurants.enter', $restaurant))
            ->assertRedirect(route('manager.dashboard'));

        $this->assertTrue(Tenancy::isImpersonating());
        $this->assertSame($restaurant->id, Tenancy::impersonatedRestaurantId());

        $this->actingAs($superAdmin)
            ->post(route('admin.restaurants.exit'))
            ->assertRedirect(route('admin.restaurants.index'));

        $this->assertFalse(Tenancy::isImpersonating());
        $this->assertNull(Tenancy::currentId());
    }

    public function test_manager_cannot_start_or_exit_super_admin_impersonation(): void
    {
        $restaurant = $this->createRestaurant(['enabled_modules' => ['menu']]);
        $manager = $this->createManager($restaurant);

        $this->actingAs($manager)
            ->post(route('admin.restaurants.enter', $restaurant))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('admin.restaurants.exit'))
            ->assertForbidden();

        $this->assertFalse(Tenancy::isImpersonating());
    }

    public function test_disabled_module_route_is_blocked_while_enabled_module_route_loads(): void
    {
        ModuleService::seedDefaultModules();

        $disabledRestaurant = $this->createRestaurant(['enabled_modules' => []]);
        $disabledManager = $this->createManager($disabledRestaurant);

        $this->actingAs($disabledManager)
            ->get(route('manager.menu-items.index'))
            ->assertForbidden();

        $enabledRestaurant = $this->createRestaurant([
            'name' => 'Enabled Restaurant',
            'slug' => 'enabled-restaurant',
            'enabled_modules' => ['menu'],
        ]);
        $enabledManager = $this->createManager($enabledRestaurant, ['menu']);

        $this->actingAs($enabledManager)
            ->get(route('manager.menu-items.index'))
            ->assertOk();
    }

    public function test_professional_hospital_sidebar_groups_only_enabled_hospital_workflows(): void
    {
        ModuleService::ensureDefaults();
        $restaurant = $this->createRestaurant([
            'enabled_modules' => ModuleService::professionalHospitalModuleKeys(),
        ]);

        $html = view('manager.layout.hospital-navigation', [
            'moduleEnabled' => fn (string $key): bool => in_array($key, $restaurant->enabled_modules, true),
        ])->render();

        $this->assertStringContainsString('Pharmacy', $html);
        $this->assertStringContainsString('Laboratory &amp; Diagnostics', $html);
        $this->assertStringContainsString('Inpatient Care', $html);
        $this->assertStringContainsString('Operating Theatre', $html);
        $this->assertStringContainsString('Billing &amp; Insurance', $html);
        $this->assertStringContainsString('Reports', $html);
        $this->assertStringContainsString(route('manager.admissions.index'), $html);
        $this->assertStringContainsString(route('manager.hospital-billing.index'), $html);
        $this->assertStringNotContainsString('Hospital Blood Bank', $html);
    }

    private function createRestaurant(array $attributes = []): Restaurant
    {
        $restaurant = Restaurant::create(array_merge([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['menu'],
        ], $attributes));

        $restaurant->subscription()->create([
            'subscription_plan_id' => null,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        return $restaurant;
    }

    private function createManager(Restaurant $restaurant, ?array $moduleAccess = null): User
    {
        return User::factory()->create([
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'module_access' => $moduleAccess,
            'phone' => '030000' . random_int(1000000, 9999999),
        ]);
    }
}
