<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Patient;
use App\Models\HospitalAdmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_radiology_routes_require_the_module(): void
    {
        $restaurant = Restaurant::create(['name' => 'Clinic Tenant', 'slug' => 'rad-module-tenant', 'status' => 'active', 'enabled_modules' => []]);
        $user = User::create(['name' => 'Manager', 'email' => 'rad-module@example.com', 'phone' => '03000000031', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);

        $this->actingAs($user)->get(route('manager.radiology.index'))->assertForbidden();
    }

    public function test_receptionist_pharmacist_and_guard_cannot_access_radiology(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'rad-clinical-denial', 'status' => 'active', 'enabled_modules' => ['hospital-radiology']]);
        foreach (['receptionist', 'pharmacist', 'guard'] as $type) {
            $user = User::create(['name' => ucfirst($type), 'email' => 'lab-'.$type.'@example.com', 'phone' => '0300000003'.strlen($type), 'role' => 'staff', 'staff_type' => $type, 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
            $this->actingAs($user)->get(route('manager.radiology.index'))->assertForbidden();
        }
    }

    public function test_doctor_can_view_radiology_orders(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'rad-doctor-access', 'status' => 'active', 'enabled_modules' => ['hospital-radiology']]);
        $user = User::create(['name' => 'Doctor', 'email' => 'rad-doctor@example.com', 'phone' => '03000000039', 'role' => 'staff', 'staff_type' => 'doctor', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);

        $this->actingAs($user)->get(route('manager.radiology.index'))->assertOk()->assertSee('Radiology orders');
    }

    public function test_manager_can_create_order_and_manage_its_result(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'rad-manager-flow', 'status' => 'active', 'enabled_modules' => ['hospital-radiology']]);
        $user = User::create(['name' => 'Owner', 'email' => 'rad-owner@example.com', 'phone' => '03000000040', 'role' => 'admin', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-LAB-1', 'name' => 'Lab Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-LAB-1', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);

        $this->actingAs($user)->post(route('manager.radiology.orders.store'), [
            'hospital_admission_id' => $admission->id,
            'study_name' => 'Chest X-ray',
            'priority' => 'routine',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $order = \App\Models\RadiologyOrder::firstOrFail();

        $this->actingAs($user)->post(route('manager.radiology.results.store', $order), [
            'result_value' => 'Within reference range',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'verified',
        ])->assertRedirect();
        $this->assertDatabaseHas('radiology_results', ['radiology_order_id' => $order->id, 'status' => 'verified']);
    }
}
