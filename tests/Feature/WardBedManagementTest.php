<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Bed;
use App\Models\HospitalAdmission;
use App\Models\Patient;
use App\Models\Ward;
use App\Models\WardLocation;
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

    public function test_manager_can_create_a_room_location_and_assign_a_bed_without_changing_ward_compatibility(): void
    {
        $restaurant = Restaurant::create(['name' => 'Location Hospital', 'slug' => 'location-hospital-' . uniqid(), 'status' => 'active', 'enabled_modules' => ['hospital-wards-beds', 'hospital-admissions']]);
        $manager = User::create(['name' => 'Manager', 'email' => uniqid('location-') . '@example.com', 'phone' => '03000' . random_int(100000, 999999), 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $ward = Ward::create(['restaurant_id' => $restaurant->id, 'name' => 'Medical Ward', 'code' => 'MEDICAL']);

        $this->actingAs($manager)->post(route('manager.wards.locations.store', $ward), [
            'type' => 'room',
            'name' => 'Room 01',
            'code' => 'ROOM-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $location = WardLocation::firstOrFail();
        $this->actingAs($manager)->post(route('manager.wards.beds.store', $ward), [
            'name' => 'Bed 01',
            'code' => 'BED-01',
            'location_id' => $location->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $bed = Bed::firstOrFail();
        $this->assertSame($ward->id, $bed->ward_id);
        $this->assertSame($location->id, $bed->location_id);
        $this->assertSame($ward->id, $bed->location->ward_id);
    }

    public function test_admission_bed_assignment_accepts_a_location_bed_and_keeps_bed_id_contract(): void
    {
        $restaurant = Restaurant::create(['name' => 'Assignment Hospital', 'slug' => 'assignment-hospital-' . uniqid(), 'status' => 'active', 'enabled_modules' => ['hospital-wards-beds', 'hospital-admissions']]);
        $manager = User::create(['name' => 'Manager', 'email' => uniqid('assignment-') . '@example.com', 'phone' => '03000' . random_int(100000, 999999), 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-' . strtoupper(substr(uniqid(), -8)), 'name' => 'Location Patient']);
        $ward = Ward::create(['restaurant_id' => $restaurant->id, 'name' => 'Surgical Ward', 'code' => 'SURGICAL']);
        $location = WardLocation::create(['restaurant_id' => $restaurant->id, 'ward_id' => $ward->id, 'type' => 'bay', 'name' => 'Bay A', 'code' => 'BAY-A']);
        $bed = Bed::create(['restaurant_id' => $restaurant->id, 'ward_id' => $ward->id, 'location_id' => $location->id, 'name' => 'Bed 02', 'code' => 'BED-02', 'status' => 'available']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-' . strtoupper(substr(uniqid(), -8)), 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);

        $this->actingAs($manager)->patch(route('manager.admissions.bed', $admission), [
            'bed_id' => $bed->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('hospital_admissions', ['id' => $admission->id, 'bed_id' => $bed->id]);
        $this->assertDatabaseHas('beds', ['id' => $bed->id, 'ward_id' => $ward->id, 'location_id' => $location->id, 'status' => 'occupied']);
    }

    public function test_location_creation_rejects_a_ward_from_another_tenant(): void
    {
        $tenantA = Restaurant::create(['name' => 'Tenant A', 'slug' => 'location-tenant-a-' . uniqid(), 'status' => 'active', 'enabled_modules' => ['hospital-wards-beds']]);
        $tenantB = Restaurant::create(['name' => 'Tenant B', 'slug' => 'location-tenant-b-' . uniqid(), 'status' => 'active', 'enabled_modules' => ['hospital-wards-beds']]);
        $manager = User::create(['name' => 'Manager', 'email' => uniqid('location-cross-tenant-') . '@example.com', 'phone' => '03000' . random_int(100000, 999999), 'role' => 'manager', 'restaurant_id' => $tenantA->id, 'password' => bcrypt('password')]);
        $ward = Ward::create(['restaurant_id' => $tenantB->id, 'name' => 'Private Ward', 'code' => 'PRIVATE']);

        $this->actingAs($manager)->post(route('manager.wards.locations.store', $ward), [
            'type' => 'room',
            'name' => 'Private Room',
            'code' => 'PRIVATE-ROOM',
        ])->assertNotFound();

        $this->assertDatabaseMissing('ward_locations', ['code' => 'PRIVATE-ROOM']);
    }

    public function test_bed_creation_rejects_a_location_from_another_tenant(): void
    {
        $tenantA = Restaurant::create(['name' => 'Tenant A', 'slug' => 'bed-location-tenant-a-' . uniqid(), 'status' => 'active', 'enabled_modules' => ['hospital-wards-beds']]);
        $tenantB = Restaurant::create(['name' => 'Tenant B', 'slug' => 'bed-location-tenant-b-' . uniqid(), 'status' => 'active', 'enabled_modules' => ['hospital-wards-beds']]);
        $manager = User::create(['name' => 'Manager', 'email' => uniqid('bed-location-cross-tenant-') . '@example.com', 'phone' => '03000' . random_int(100000, 999999), 'role' => 'manager', 'restaurant_id' => $tenantA->id, 'password' => bcrypt('password')]);
        $ward = Ward::create(['restaurant_id' => $tenantA->id, 'name' => 'Local Ward', 'code' => 'LOCAL']);
        $otherWard = Ward::create(['restaurant_id' => $tenantB->id, 'name' => 'Other Ward', 'code' => 'OTHER']);
        $location = WardLocation::create(['restaurant_id' => $tenantB->id, 'ward_id' => $otherWard->id, 'type' => 'hall', 'name' => 'Other Hall', 'code' => 'OTHER-HALL']);

        $this->actingAs($manager)->post(route('manager.wards.beds.store', $ward), [
            'name' => 'Bed 01',
            'code' => 'LOCAL-01',
            'location_id' => $location->id,
        ])->assertSessionHasErrors('location_id');

        $this->assertDatabaseMissing('beds', ['code' => 'LOCAL-01']);
    }
}
