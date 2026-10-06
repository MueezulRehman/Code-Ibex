<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RestaurantSubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_restaurant_with_subscription_plan(): void
    {
        config(['tenancy.auto_provision' => true]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'admin@example.com',
            'phone' => '10000000001',
        ]);

        $businessType = BusinessType::create([
            'name' => 'Cafe',
            'slug' => 'cafe',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Starter plan',
            'price_monthly' => 15,
            'price_yearly' => 150,
            'trial_days' => 14,
            'max_staff' => 5,
            'max_menu_items' => 100,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('provision')
            ->once()
            ->with(Mockery::on(fn (Restaurant $restaurant): bool => $restaurant->slug === 'plan-test-restaurant'
                && ! $restaurant->hasTenantDatabase()))
            ->andReturn(true);
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $response = $this->actingAs($superAdmin)->post(route('admin.restaurants.store'), [
            'name' => 'Plan Test Restaurant',
            'slug' => 'plan-test-restaurant',
            'business_type_id' => $businessType->id,
            'status' => 'active',
            'plan' => $plan->slug,
            'enabled_modules' => [],
        ]);

        $response->assertRedirect(route('admin.restaurants.index'));

        $restaurant = Restaurant::where('slug', 'plan-test-restaurant')->firstOrFail();

        $this->assertNotNull($restaurant->subscription);
        $this->assertEquals($plan->id, $restaurant->subscription->subscription_plan_id);
        $this->assertEquals('active', $restaurant->subscription->status);
    }

    public function test_super_admin_can_create_restaurant_when_plan_is_blank(): void
    {
        config(['tenancy.auto_provision' => false]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'admin-no-plan@example.com',
            'phone' => '10000000002',
        ]);

        $businessType = BusinessType::create([
            'name' => 'Cafe Without Plan',
            'slug' => 'cafe-without-plan',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Default Starter',
            'slug' => 'default-starter',
            'description' => 'Default plan used when the plan field is blank',
            'price_monthly' => 15,
            'price_yearly' => 150,
            'trial_days' => 14,
            'max_staff' => 5,
            'max_menu_items' => 100,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.restaurants.store'), [
            'name' => 'Plan Omitted Restaurant',
            'slug' => 'plan-omitted-restaurant',
            'business_type_id' => $businessType->id,
            'status' => 'active',
            'plan' => '',
            'enabled_modules' => [],
        ]);

        $response->assertRedirect(route('admin.restaurants.index'));

        $restaurant = Restaurant::where('slug', 'plan-omitted-restaurant')->firstOrFail();

        $this->assertSame($plan->slug, $restaurant->plan);
        $this->assertNotNull($restaurant->subscription);
        $this->assertSame($plan->id, $restaurant->subscription->subscription_plan_id);
    }

    public function test_tenant_provisioning_failure_is_reported_after_business_creation(): void
    {
        config(['tenancy.auto_provision' => true]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'admin-provision-failure@example.com',
            'phone' => '10000000003',
        ]);
        $businessType = BusinessType::create([
            'name' => 'Cafe Provision Failure',
            'slug' => 'cafe-provision-failure',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('provision')
            ->once()
            ->andThrow(new \RuntimeException('CREATE DATABASE denied'));
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $response = $this->actingAs($superAdmin)->post(route('admin.restaurants.store'), [
            'name' => 'Provision Failure Restaurant',
            'slug' => 'provision-failure-restaurant',
            'business_type_id' => $businessType->id,
            'status' => 'active',
            'enabled_modules' => [],
        ]);

        $response->assertRedirect(route('admin.restaurants.index'));
        $restaurant = Restaurant::where('slug', 'provision-failure-restaurant')->firstOrFail();
        $response->assertSessionHas('error', fn (string $error): bool => str_contains($error, "Business #{$restaurant->id}")
            && str_contains($error, "php artisan tenants:migrate {$restaurant->id}"));
        $this->assertFalse($restaurant->hasTenantDatabase());
    }
}
