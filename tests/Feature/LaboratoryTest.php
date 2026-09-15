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

class LaboratoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_order_only_for_direct_or_department_assignments(): void
    {
        $restaurant = Restaurant::create(['name' => 'Scoped Hospital', 'slug' => 'lab-doctor-scope', 'status' => 'active', 'enabled_modules' => ['hospital-laboratory']]);
        $department = Department::create(['restaurant_id' => $restaurant->id, 'name' => 'Cardiology', 'code' => 'CARD', 'is_active' => true]);
        $doctorUser = User::create(['name' => 'Assigned Doctor', 'email' => 'lab-scoped@example.com', 'phone' => '03000000101', 'role' => 'staff', 'staff_type' => 'doctor', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $doctor = Doctor::create(['restaurant_id' => $restaurant->id, 'user_id' => $doctorUser->id, 'name' => 'Assigned Doctor', 'specialty' => 'Cardiology', 'status' => 'active', 'department_id' => $department->id]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-LAB-SCOPE', 'name' => 'Scoped Patient']);
        $allowed = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-LAB-ALLOWED', 'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'department_id' => $department->id, 'status' => 'admitted', 'admitted_at' => now()]);
        $denied = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-LAB-DENIED', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);

        $payload = fn (int $admissionId) => ['hospital_admission_id' => $admissionId, 'test_name' => 'CBC', 'priority' => 'routine', 'ordered_at' => now()->format('Y-m-d H:i:s')];
        $this->actingAs($doctorUser)->post(route('manager.laboratory.orders.store'), $payload($allowed->id))->assertRedirect();
        $this->actingAs($doctorUser)->post(route('manager.laboratory.orders.store'), $payload($denied->id))->assertForbidden();
    }

    public function test_laboratory_routes_require_the_module(): void
    {
        $restaurant = Restaurant::create(['name' => 'Clinic Tenant', 'slug' => 'lab-module-tenant', 'status' => 'active', 'enabled_modules' => []]);
        $user = User::create(['name' => 'Manager', 'email' => 'lab-module@example.com', 'phone' => '03000000031', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);

        $this->actingAs($user)->get(route('manager.laboratory.index'))->assertForbidden();
    }

    public function test_receptionist_pharmacist_and_guard_cannot_access_laboratory(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'lab-clinical-denial', 'status' => 'active', 'enabled_modules' => ['hospital-laboratory']]);
        foreach (['receptionist', 'pharmacist', 'guard'] as $type) {
            $user = User::create(['name' => ucfirst($type), 'email' => 'lab-'.$type.'@example.com', 'phone' => '0300000003'.strlen($type), 'role' => 'staff', 'staff_type' => $type, 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
            $this->actingAs($user)->get(route('manager.laboratory.index'))->assertForbidden();
        }
    }

    public function test_doctor_can_view_laboratory_orders(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'lab-doctor-access', 'status' => 'active', 'enabled_modules' => ['hospital-laboratory']]);
        $user = User::create(['name' => 'Doctor', 'email' => 'lab-doctor@example.com', 'phone' => '03000000039', 'role' => 'staff', 'staff_type' => 'doctor', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);

        $this->actingAs($user)->get(route('manager.laboratory.index'))->assertOk()->assertSee('Laboratory orders');
    }

    public function test_manager_can_create_order_and_manage_its_result(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'lab-manager-flow', 'status' => 'active', 'enabled_modules' => ['hospital-laboratory']]);
        $user = User::create(['name' => 'Owner', 'email' => 'lab-owner@example.com', 'phone' => '03000000040', 'role' => 'admin', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-LAB-1', 'name' => 'Lab Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-LAB-1', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);

        $this->actingAs($user)->post(route('manager.laboratory.orders.store'), [
            'hospital_admission_id' => $admission->id,
            'test_name' => 'Complete blood count',
            'priority' => 'routine',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $order = \App\Models\LabOrder::firstOrFail();

        $this->actingAs($user)->post(route('manager.laboratory.results.store', $order), [
            'result_value' => 'Within reference range',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'verified',
        ])->assertRedirect();
        $this->assertDatabaseHas('lab_results', ['lab_order_id' => $order->id, 'status' => 'verified']);
    }

    public function test_manager_result_edit_creates_audit_history(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'lab-audit-tenant', 'status' => 'active', 'enabled_modules' => ['hospital-laboratory']]);
        $manager = User::create(['name' => 'Owner', 'email' => 'lab-audit-owner@example.com', 'phone' => '03000000041', 'role' => 'admin', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-LAB-2', 'name' => 'Audit Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-LAB-2', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);

        $this->actingAs($manager)->post(route('manager.laboratory.orders.store'), [
            'hospital_admission_id' => $admission->id,
            'test_name' => 'Hemoglobin',
            'priority' => 'urgent',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $order = \App\Models\LabOrder::firstOrFail();

        $this->actingAs($manager)->post(route('manager.laboratory.results.store', $order), [
            'result_value' => '12.2',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'entered',
        ]);
        $result = $order->result()->firstOrFail();

        $this->actingAs($manager)->patch(route('manager.laboratory.results.update', $result), [
            'result_value' => '12.8',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'verified',
            'notes' => 'Corrected after review.',
            'correction_notes' => 'Verified with repeat lab check.',
        ])->assertRedirect();

        $this->assertDatabaseHas('lab_result_events', ['lab_result_id' => $result->id, 'action' => 'verified']);
        $this->assertSame('12.8', $result->fresh()->result_value);
    }

    public function test_non_managers_cannot_correct_laboratory_results(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'lab-edit-denied', 'status' => 'active', 'enabled_modules' => ['hospital-laboratory']]);
        $manager = User::create(['name' => 'Manager', 'email' => 'lab-edit-manager@example.com', 'phone' => '03000000042', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $doctor = User::create(['name' => 'Doctor', 'email' => 'lab-edit-doctor@example.com', 'phone' => '03000000043', 'role' => 'staff', 'staff_type' => 'doctor', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-LAB-3', 'name' => 'Restricted Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-LAB-3', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);

        $this->actingAs($manager)->post(route('manager.laboratory.orders.store'), [
            'hospital_admission_id' => $admission->id,
            'test_name' => 'Liver enzymes',
            'priority' => 'routine',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $order = \App\Models\LabOrder::firstOrFail();
        $result = $order->result()->create([
            'restaurant_id' => $restaurant->id,
            'patient_id' => $patient->id,
            'hospital_admission_id' => $admission->id,
            'result_value' => 'Normal',
            'status' => 'entered',
            'resulted_at' => now(),
            'entered_by' => $manager->id,
        ]);

        $this->actingAs($doctor)->patch(route('manager.laboratory.results.update', $result), [
            'result_value' => 'Abnormal',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'verified',
            'notes' => 'Not allowed',
            'correction_notes' => 'Without permission',
        ])->assertForbidden();
    }

    public function test_cross_tenant_result_update_is_rejected(): void
    {
        $tenantA = Restaurant::create(['name' => 'Hospital A', 'slug' => 'lab-tenant-a', 'status' => 'active', 'enabled_modules' => ['hospital-laboratory']]);
        $tenantB = Restaurant::create(['name' => 'Hospital B', 'slug' => 'lab-tenant-b', 'status' => 'active', 'enabled_modules' => ['hospital-laboratory']]);
        $managerA = User::create(['name' => 'Tenant A Manager', 'email' => 'lab-cross-a@example.com', 'phone' => '03000000044', 'role' => 'manager', 'restaurant_id' => $tenantA->id, 'password' => bcrypt('password')]);
        $managerB = User::create(['name' => 'Tenant B Manager', 'email' => 'lab-cross-b@example.com', 'phone' => '03000000045', 'role' => 'manager', 'restaurant_id' => $tenantB->id, 'password' => bcrypt('password')]);
        $patientB = Patient::create(['restaurant_id' => $tenantB->id, 'patient_number' => 'PAT-LAB-4', 'name' => 'Tenant B Patient']);
        $admissionB = HospitalAdmission::create(['restaurant_id' => $tenantB->id, 'admission_number' => 'ADM-LAB-4', 'patient_id' => $patientB->id, 'status' => 'admitted', 'admitted_at' => now()]);
        $order = \App\Models\LabOrder::create([
            'restaurant_id' => $tenantB->id,
            'patient_id' => $patientB->id,
            'hospital_admission_id' => $admissionB->id,
            'ordered_by' => $managerB->id,
            'order_number' => 'LAB-CROSS-1',
            'test_name' => 'Troponin',
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

        $this->actingAs($managerA)->patch(route('manager.laboratory.results.update', $result), [
            'result_value' => 'High',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'verified',
            'notes' => 'Should fail',
            'correction_notes' => 'Attempted cross-tenant edit',
        ])->assertNotFound();
    }

    public function test_lab_result_rejects_silent_edits(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital Tenant', 'slug' => 'lab-silent-edit', 'status' => 'active', 'enabled_modules' => ['hospital-laboratory']]);
        $manager = User::create(['name' => 'Manager', 'email' => 'lab-silent@example.com', 'phone' => '03000000046', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-LAB-5', 'name' => 'Silent Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-LAB-5', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()]);
        $this->actingAs($manager)->post(route('manager.laboratory.orders.store'), [
            'hospital_admission_id' => $admission->id,
            'test_name' => 'Calcium',
            'priority' => 'stat',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $order = \App\Models\LabOrder::firstOrFail();
        $this->actingAs($manager)->post(route('manager.laboratory.results.store', $order), [
            'result_value' => '9.5',
            'resulted_at' => now()->format('Y-m-d H:i:s'),
            'status' => 'entered',
        ]);
        $result = $order->result()->firstOrFail();

        $this->actingAs($manager)->patch(route('manager.laboratory.results.update', $result), [
            'result_value' => '9.5',
            'resulted_at' => $result->resulted_at->format('Y-m-d H:i:s'),
            'status' => 'entered',
            'notes' => $result->notes,
            'correction_notes' => 'No actual change',
        ])->assertRedirect()->assertSessionHasErrors('result_value');

        $this->assertDatabaseCount('lab_result_events', 1);
    }
}
