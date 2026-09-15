<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalClinicalNote;
use App\Models\NursingAssignment;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalClinicalNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_gate_and_staff_authorization_are_enforced(): void
    {
        $restaurant = $this->restaurant(['hospital-clinical-notes']);
        $receptionist = $this->user($restaurant, ['role' => 'staff', 'staff_type' => 'receptionist']);
        $this->actingAs($receptionist)->get(route('manager.hospital-clinical-notes.index'))->assertForbidden();

        $disabled = $this->restaurant([]);
        $manager = $this->user($disabled, ['role' => 'manager']);
        $this->actingAs($manager)->get(route('manager.hospital-clinical-notes.index'))->assertForbidden();
    }

    public function test_assigned_doctor_and_nurse_can_create_only_their_note_types(): void
    {
        $restaurant = $this->restaurant(['hospital-clinical-notes']);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'P-1', 'name' => 'Patient One']);
        $doctorUser = $this->user($restaurant, ['role' => 'staff', 'staff_type' => 'doctor']);
        $doctor = Doctor::create(['restaurant_id' => $restaurant->id, 'user_id' => $doctorUser->id, 'name' => 'Doctor One', 'specialty' => 'General Medicine', 'status' => 'active']);
        $admission = $this->admission($restaurant, $patient, $doctor->id);
        $nurse = $this->user($restaurant, ['role' => 'staff', 'staff_type' => 'nurse']);
        NursingAssignment::create([
            'restaurant_id' => $restaurant->id, 'hospital_admission_id' => $admission->id,
            'nurse_id' => $nurse->id, 'assigned_at' => '2026-09-15 09:00:00',
            'status' => 'active', 'shift' => 'morning',
        ]);

        $this->actingAs($doctorUser)->post(route('manager.hospital-clinical-notes.store'), [
            'hospital_admission_id' => $admission->id, 'note_type' => 'progress',
            'content' => 'Doctor progress note', 'recorded_at' => '2026-09-15 10:00:00',
        ])->assertSessionHasNoErrors();
        $this->actingAs($doctorUser)->post(route('manager.hospital-clinical-notes.store'), [
            'hospital_admission_id' => $admission->id, 'note_type' => 'nursing',
            'content' => 'Not allowed', 'recorded_at' => '2026-09-15 10:00:00',
        ])->assertForbidden();

        $this->actingAs($nurse)->post(route('manager.hospital-clinical-notes.store'), [
            'hospital_admission_id' => $admission->id, 'note_type' => 'nursing',
            'content' => 'Nursing observation', 'recorded_at' => '2026-09-15 11:00:00',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, HospitalClinicalNote::count());
    }

    public function test_note_timestamps_are_inside_the_admission_window(): void
    {
        $restaurant = $this->restaurant(['hospital-clinical-notes']);
        $manager = $this->user($restaurant, ['role' => 'manager']);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'P-2', 'name' => 'Patient Two']);
        $admission = $this->admission($restaurant, $patient, null, '2026-09-15 10:00:00', '2026-09-15 12:00:00');

        $this->actingAs($manager)->post(route('manager.hospital-clinical-notes.store'), [
            'hospital_admission_id' => $admission->id, 'note_type' => 'clinical',
            'content' => 'Too early', 'recorded_at' => '2026-09-15 09:59:00',
        ])->assertSessionHasErrors('recorded_at');
        $this->actingAs($manager)->post(route('manager.hospital-clinical-notes.store'), [
            'hospital_admission_id' => $admission->id, 'note_type' => 'clinical',
            'content' => 'Too late', 'recorded_at' => '2026-09-15 12:01:00',
        ])->assertSessionHasErrors('recorded_at');
    }

    public function test_manager_correction_and_void_write_audit_events(): void
    {
        $restaurant = $this->restaurant(['hospital-clinical-notes']);
        $manager = $this->user($restaurant, ['role' => 'manager']);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'P-3', 'name' => 'Patient Three']);
        $admission = $this->admission($restaurant, $patient);
        $this->actingAs($manager)->post(route('manager.hospital-clinical-notes.store'), [
            'hospital_admission_id' => $admission->id, 'note_type' => 'clinical',
            'content' => 'Original', 'recorded_at' => '2026-09-15 10:00:00',
        ]);
        $note = HospitalClinicalNote::firstOrFail();

        $this->actingAs($manager)->patch(route('manager.hospital-clinical-notes.update', $note), [
            'content' => 'Corrected', 'recorded_at' => '2026-09-15 10:30:00', 'correction_notes' => 'Clarified finding',
        ])->assertSessionHasNoErrors();
        $this->actingAs($manager)->patch(route('manager.hospital-clinical-notes.void', $note), [
            'void_reason' => 'Entered in error',
        ])->assertSessionHasNoErrors();
        $this->assertSame('void', $note->fresh()->status);
        $this->assertSame(['recorded', 'corrected', 'voided'], HospitalClinicalNote::first()->events()->oldest('id')->pluck('action')->all());
    }

    public function test_tenant_cannot_read_another_tenants_note(): void
    {
        $first = $this->restaurant(['hospital-clinical-notes']);
        $manager = $this->user($first, ['role' => 'manager']);
        $patient = Patient::create(['restaurant_id' => $first->id, 'patient_number' => 'P-4', 'name' => 'Patient Four']);
        $admission = $this->admission($first, $patient);
        $this->actingAs($manager)->post(route('manager.hospital-clinical-notes.store'), [
            'hospital_admission_id' => $admission->id, 'note_type' => 'clinical',
            'content' => 'Tenant one', 'recorded_at' => '2026-09-15 10:00:00',
        ]);
        $note = HospitalClinicalNote::firstOrFail();
        $second = $this->restaurant(['hospital-clinical-notes']);
        $otherManager = $this->user($second, ['role' => 'manager']);

        $this->actingAs($otherManager)->patch(route('manager.hospital-clinical-notes.update', $note), [
            'content' => 'Cross tenant', 'recorded_at' => '2026-09-15 10:00:00', 'correction_notes' => 'No',
        ])->assertNotFound();
    }

    private function restaurant(array $modules): Restaurant
    {
        return Restaurant::create(['name' => 'Hospital ' . uniqid(), 'slug' => 'hospital-' . uniqid(), 'status' => 'active', 'enabled_modules' => $modules]);
    }

    private function user(Restaurant $restaurant, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Staff ' . uniqid(), 'email' => uniqid() . '@example.com',
            'phone' => '03' . random_int(100000000, 999999999), 'role' => 'admin',
            'restaurant_id' => $restaurant->id, 'password' => bcrypt('password'), 'is_active' => true,
        ], $attributes));
    }

    private function admission(Restaurant $restaurant, Patient $patient, ?int $doctorId = null, string $admittedAt = '2026-09-15 09:00:00', ?string $dischargedAt = null): HospitalAdmission
    {
        return HospitalAdmission::create([
            'restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-' . uniqid(),
            'patient_id' => $patient->id, 'doctor_id' => $doctorId, 'status' => $dischargedAt ? 'discharged' : 'admitted',
            'admitted_at' => $admittedAt, 'discharged_at' => $dischargedAt,
        ]);
    }
}
