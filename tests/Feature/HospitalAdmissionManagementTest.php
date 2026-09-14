<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalAdmissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_routes_require_the_hospital_admissions_module(): void
    {
        $restaurant = Restaurant::create(['name' => 'Clinic Tenant', 'slug' => 'clinic-tenant', 'status' => 'active', 'enabled_modules' => ['medical']]);
        $user = User::create(['name' => 'Manager', 'email' => 'admission-block@example.com', 'phone' => '03000000009', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);

        $this->actingAs($user)->get(route('manager.admissions.index'))->assertForbidden();
        $this->actingAs($user)->get(route('manager.admissions.create'))->assertForbidden();
    }

}
