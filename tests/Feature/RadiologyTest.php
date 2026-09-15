<?php

namespace Tests\Feature;

use App\Models\HospitalAdmission;
use App\Models\Doctor;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_cannot_order_radiology_for_an_unassigned_admission(): void
    {
        $restaurant = Restaurant::create(['name' => 'Scoped Hospital', 'slug' => 'rad-doctor-scope', 'status' => 'active', 'enabled_modules' => ['hospital-radiology']]);
        $department = Department::create(['restaurant_id' => $restaurant->id, 'name' => 'Imaging', 'code' => 'IMG', 'is_active' => true]);
        $doctorUser = User::create(['name' => 'Assigned Radiologist', 'email' => 'rad-scoped@example.com', 'phone' => '03000000102', 'role' => 'staff', 'staff_type' => 'doctor', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $doctor = Doctor::create(['restaurant_id' => $restaurant->id, 'user_id' => $doctorUser->id, 'name' => 'Assigned Radiologist', 'specialty' => 'Radiology', 'status' => 'active', 'department_id' => $department->id]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-RAD-SCOPE', 'name' => 'Scoped Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-RAD-DENIED', 'patient_id' => $patient->id, 'doctor_id' => null, 'department_id' => null, 'status' => 'admitted', 'admitted_at' => now()]);

        $this->actingAs($doctorUser)->post(route('manager.radiology.orders.store'), [
            'hospital_admission_id' => $admission->id,
            'study_name' => 'Chest X-ray',
            'priority' => 'routine',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ])->assertForbidden();
    }

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
            $user = User::create(['name' => ucfirst($type), 'email' => 'rad-'.$type.'@example.com', 'phone' => '0300000003'.strlen($type), 'role' => 'staff', 'staff_type' => $type, 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
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
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-RAD-1', 'name' => 'Rad Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-RAD-1', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);

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

    public function test_manager_result_edit_creates_audit_history(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'rad-audit-tenant', 'status' => 'active', 'enabled_modules' => ['hospital-radiology']]);
        $manager = User::create(['name' => 'Owner', 'email' => 'rad-audit-owner@example.com', 'phone' => '03000000041', 'role' => 'admin', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-RAD-2', 'name' => 'Audit Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-RAD-2', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);

        $this->actingAs($manager)->post(route('manager.radiology.orders.store'), [
            'hospital_admission_id' => $admission->id,
            'study_name' => 'Brain MRI',
            'priority' => 'urgent',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $order = \App\Models\RadiologyOrder::firstOrFail();

        $this->actingAs($manager)->post(route('manager.radiology.results.store', $order), [
            'result_value' => 'Mild inflammation',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'entered',
        ]);
        $result = $order->result()->firstOrFail();

        $this->actingAs($manager)->patch(route('manager.radiology.results.update', $result), [
            'result_value' => 'No acute inflammation',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'verified',
            'notes' => 'Corrected after review.',
            'correction_notes' => 'Confirmed with repeat imaging.',
        ])->assertRedirect();

        $this->assertDatabaseHas('radiology_result_events', ['radiology_result_id' => $result->id, 'action' => 'verified']);
        $this->assertSame('No acute inflammation', $result->fresh()->result_value);
    }

    public function test_non_managers_cannot_correct_radiology_results(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'rad-edit-denied', 'status' => 'active', 'enabled_modules' => ['hospital-radiology']]);
        $manager = User::create(['name' => 'Manager', 'email' => 'rad-edit-manager@example.com', 'phone' => '03000000042', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $doctor = User::create(['name' => 'Doctor', 'email' => 'rad-edit-doctor@example.com', 'phone' => '03000000043', 'role' => 'staff', 'staff_type' => 'doctor', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-RAD-3', 'name' => 'Restricted Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-RAD-3', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);

        $this->actingAs($manager)->post(route('manager.radiology.orders.store'), [
            'hospital_admission_id' => $admission->id,
            'study_name' => 'Pelvic MRI',
            'priority' => 'routine',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $order = \App\Models\RadiologyOrder::firstOrFail();
        $result = $order->result()->create([
            'restaurant_id' => $restaurant->id,
            'patient_id' => $patient->id,
            'hospital_admission_id' => $admission->id,
            'result_value' => 'Normal',
            'status' => 'entered',
            'resulted_at' => now(),
            'entered_by' => $manager->id,
        ]);

        $this->actingAs($doctor)->patch(route('manager.radiology.results.update', $result), [
            'result_value' => 'Abnormal',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'verified',
            'notes' => 'Not allowed',
            'correction_notes' => 'Without permission',
        ])->assertForbidden();
    }

    public function test_cross_tenant_result_update_is_rejected(): void
    {
        $tenantA = Restaurant::create(['name' => 'Hospital A', 'slug' => 'rad-tenant-a', 'status' => 'active', 'enabled_modules' => ['hospital-radiology']]);
        $tenantB = Restaurant::create(['name' => 'Hospital B', 'slug' => 'rad-tenant-b', 'status' => 'active', 'enabled_modules' => ['hospital-radiology']]);
        $managerA = User::create(['name' => 'Tenant A Manager', 'email' => 'rad-cross-a@example.com', 'phone' => '03000000044', 'role' => 'manager', 'restaurant_id' => $tenantA->id, 'password' => bcrypt('password')]);
        $managerB = User::create(['name' => 'Tenant B Manager', 'email' => 'rad-cross-b@example.com', 'phone' => '03000000045', 'role' => 'manager', 'restaurant_id' => $tenantB->id, 'password' => bcrypt('password')]);
        $patientB = Patient::create(['restaurant_id' => $tenantB->id, 'patient_number' => 'PAT-RAD-4', 'name' => 'Tenant B Patient']);
        $admissionB = HospitalAdmission::create(['restaurant_id' => $tenantB->id, 'admission_number' => 'ADM-RAD-4', 'patient_id' => $patientB->id, 'status' => 'admitted', 'admitted_at' => now()]);
        $order = \App\Models\RadiologyOrder::create([
            'restaurant_id' => $tenantB->id,
            'patient_id' => $patientB->id,
            'hospital_admission_id' => $admissionB->id,
            'ordered_by' => $managerB->id,
            'order_number' => 'RAD-CROSS-1',
            'study_name' => 'Bone density',
            'priority' => 'routine',
            'ordered_at' => now(),
            'status' => 'ordered',
        ]);
        $result = $order->result()->create([
            'restaurant_id' => $tenantB->id,
            'patient_id' => $patientB->id,
            'hospital_admission_id' => $admissionB->id,
            'result_value' => 'Low',
            'status' => 'entered',
            'resulted_at' => now(),
            'entered_by' => $managerB->id,
        ]);

        $this->actingAs($managerA)->patch(route('manager.radiology.results.update', $result), [
            'result_value' => 'High',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'verified',
            'notes' => 'Should fail',
            'correction_notes' => 'Attempted cross-tenant edit',
        ])->assertNotFound();
    }

    public function test_radiology_result_rejects_silent_edits(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'rad-silent-edit', 'status' => 'active', 'enabled_modules' => ['hospital-radiology']]);
        $manager = User::create(['name' => 'Manager', 'email' => 'rad-silent@example.com', 'phone' => '03000000046', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-RAD-5', 'name' => 'Silent Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-RAD-5', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);
        $this->actingAs($manager)->post(route('manager.radiology.orders.store'), [
            'hospital_admission_id' => $admission->id,
            'study_name' => 'Ultrasound',
            'priority' => 'stat',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $order = \App\Models\RadiologyOrder::firstOrFail();
        $this->actingAs($manager)->post(route('manager.radiology.results.store', $order), [
            'result_value' => '9.5',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'entered',
        ]);
        $result = $order->result()->firstOrFail();

        $this->actingAs($manager)->patch(route('manager.radiology.results.update', $result), [
            'result_value' => '9.5',
            'resulted_at' => $result->resulted_at->format('Y-m-d H:i:s'),
            'status' => 'entered',
            'notes' => $result->notes,
            'correction_notes' => 'No actual change',
        ])->assertRedirect()->assertSessionHasErrors('result_value');

        $this->assertDatabaseCount('radiology_result_events', 1);
    }
}
