<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WardBedManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_ward_routes_require_the_wards_module(): void
    {
        $restaurant = Restaurant::create(['name' => 'Clinic Tenant', 'slug' => 'clinic-ward-tenant', 'status' => 'active', 'enabled_modules' => []]);
        $user = User::create(['name' => 'Manager', 'email' => 'ward-block@example.com', 'phone' => '03000000010', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $this->actingAs($user)->get(route('manager.wards.index'))->assertForbidden();
    }

    public function test_bed_creation_rejects_a_ward_from_another_tenant(): void
    {
        $tenantA = Restaurant::create(['name' => 'Tenant A', 'slug' => 'ward-tenant-a', 'status' => 'active', 'enabled_modules' => ['hospital-wards-beds']]);
        $tenantB = Restaurant::create(['name' => 'Tenant B', 'slug' => 'ward-tenant-b', 'status' => 'active', 'enabled_modules' => ['hospital-wards-beds']]);
        $manager = User::create(['name' => 'Manager', 'email' => 'ward-cross-tenant@example.com', 'phone' => '03000000011', 'role' => 'manager', 'restaurant_id' => $tenantA->id, 'password' => bcrypt('password')]);
        $ward = Ward::create(['restaurant_id' => $tenantB->id, 'name' => 'Private Ward', 'code' => 'PRIVATE']);

        $this->actingAs($manager)->post(route('manager.wards.beds.store', $ward), [
            'name' => 'Bed 1',
            'code' => 'PRIVATE-01',
        ])->assertNotFound();
    }
}
