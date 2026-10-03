<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalEmergencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_register_and_route_emergency_case_into_active_admission_with_billing(): void
    {
        ModuleService::ensureDefaults();
        $type = BusinessType::where('name', 'Professional Hospital')->firstOrFail();
        $restaurant = Restaurant::create([
            'business_type_id' => $type->id,
            'name' => 'Emergency Test Hospital',
            'slug' => 'emergency-test-hospital',
            'status' => 'active',
            'enabled_modules' => ModuleService::professionalHospitalModuleKeys(),
        ]);
        $manager = User::factory()->create([
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'phone' => '03001234567',
        ]);
        $patient = Patient::create([
            'restaurant_id' => $restaurant->id,
            'patient_number' => 'PAT-ER-1',
            'name' => 'Emergency Patient',
            'phone' => '03009999999',
        ]);
        $doctor = Doctor::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Emergency Doctor',
            'status' => 'active',
        ]);
        $department = Department::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Emergency Department',
            'code' => 'ER',
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->post(route('manager.hospital-emergency.store'), [
            'patient_id' => $patient->id,
            'priority' => 'critical',
            'arrived_at' => '2026-09-16 10:00:00',
            'chief_complaint' => 'Chest pain',
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'emergency_charge_amount' => 2500,
        ]);

        $response->assertRedirect(route('manager.hospital-emergency.index'));
        $this->assertDatabaseHas('hospital_emergency_cases', [
            'restaurant_id' => $restaurant->id,
            'patient_id' => $patient->id,
            'priority' => 'critical',
            'status' => 'waiting',
        ]);

        $case = \App\Models\HospitalEmergencyCase::firstOrFail();
        $this->actingAs($manager)->patch(route('manager.hospital-emergency.route', $case), [
            'destination' => 'observation',
            'department_id' => $department->id,
            'admitted_at' => '2026-09-16 10:15:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('hospital_emergency_cases', [
            'id' => $case->id,
            'status' => 'routed',
            'destination' => 'observation',
        ]);
        $this->assertDatabaseHas('hospital_admissions', [
            'patient_id' => $patient->id,
            'status' => 'admitted',
        ]);
        $this->assertDatabaseHas('hospital_billings', [
            'patient_id' => $patient->id,
            'charge_type' => 'emergency',
            'total_amount' => 2500,
        ]);
    }

    public function test_emergency_cannot_route_to_ward_without_a_bed(): void
    {
        ModuleService::ensureDefaults();
        $type = BusinessType::where('name', 'Professional Hospital')->firstOrFail();
        $restaurant = Restaurant::create([
            'business_type_id' => $type->id,
            'name' => 'Emergency Bed Test Hospital',
            'slug' => 'emergency-bed-test-hospital',
            'status' => 'active',
            'enabled_modules' => ModuleService::professionalHospitalModuleKeys(),
        ]);
        $manager = User::factory()->create([
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'phone' => '03001234568',
        ]);
        $patient = Patient::create([
            'restaurant_id' => $restaurant->id,
            'patient_number' => 'PAT-ER-2',
            'name' => 'Unassigned Emergency Patient',
            'phone' => '03009999998',
        ]);
        $case = \App\Models\HospitalEmergencyCase::create([
            'restaurant_id' => $restaurant->id,
            'patient_id' => $patient->id,
            'case_number' => 'ER-TEST-2',
            'priority' => 'urgent',
            'status' => 'triaged',
            'arrived_at' => now(),
            'chief_complaint' => 'Injury',
        ]);

        $this->actingAs($manager)->patch(route('manager.hospital-emergency.route', $case), [
            'destination' => 'ward',
            'admitted_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('bed_id');

        $this->assertDatabaseCount('hospital_admissions', 0);
    }

    public function test_other_tenant_cannot_access_emergency_case(): void
    {
        ModuleService::ensureDefaults();
        $type = BusinessType::where('name', 'Professional Hospital')->firstOrFail();
        $restaurantA = Restaurant::create(['business_type_id' => $type->id, 'name' => 'A', 'slug' => 'hospital-a', 'status' => 'active', 'enabled_modules' => ModuleService::professionalHospitalModuleKeys()]);
        $restaurantB = Restaurant::create(['business_type_id' => $type->id, 'name' => 'B', 'slug' => 'hospital-b', 'status' => 'active', 'enabled_modules' => ModuleService::professionalHospitalModuleKeys()]);
        $manager = User::factory()->create(['role' => 'manager', 'restaurant_id' => $restaurantA->id, 'phone' => '03001234569']);
        $patient = Patient::create(['restaurant_id' => $restaurantB->id, 'patient_number' => 'PAT-ER-3', 'name' => 'Other Tenant Patient', 'phone' => '03009999997']);
        $case = \App\Models\HospitalEmergencyCase::create([
            'restaurant_id' => $restaurantB->id,
            'patient_id' => $patient->id,
            'case_number' => 'ER-TEST-3',
            'priority' => 'routine',
            'status' => 'waiting',
            'arrived_at' => now(),
            'chief_complaint' => 'Fever',
        ]);

        $this->actingAs($manager)->patch(route('manager.hospital-emergency.triage', $case), [
            'priority' => 'urgent',
        ])->assertNotFound();
    }
}
