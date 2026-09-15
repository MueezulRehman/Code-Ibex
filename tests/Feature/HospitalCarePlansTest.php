<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalCarePlan;
use App\Models\HospitalCarePlanEvent;
use App\Models\NursingAssignment;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalCarePlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_and_staff_roles_are_enforced(): void
    {
        $restaurant = $this->restaurant(['hospital-care-plans']);
        $manager = $this->user($restaurant, ['role' => 'manager']);
        $this->actingAs($manager)->get(route('manager.hospital-care-plans.index'))->assertOk();
        $receptionist = $this->user($restaurant, ['role' => 'staff', 'staff_type' => 'receptionist']);
        $this->actingAs($receptionist)->get(route('manager.hospital-care-plans.index'))->assertForbidden();

        $disabled = $this->restaurant([]);
        $manager = $this->user($disabled, ['role' => 'manager']);
        $this->actingAs($manager)->get(route('manager.hospital-care-plans.index'))->assertForbidden();
    }

    public function test_authorized_doctor_can_create_and_assigned_nurse_can_update_only_progress(): void
    {
        $restaurant = $this->restaurant(['hospital-care-plans']);
        $doctorUser = $this->user($restaurant, ['role' => 'staff', 'staff_type' => 'doctor']);
        $doctor = Doctor::create(['restaurant_id' => $restaurant->id, 'user_id' => $doctorUser->id, 'name' => 'Doctor', 'specialty' => 'Medicine', 'status' => 'active']);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'P-' . uniqid(), 'name' => 'Patient']);
        $admission = $this->admission($restaurant, $patient, $doctor->id);
        $nurse = $this->user($restaurant, ['role' => 'staff', 'staff_type' => 'nurse']);
        NursingAssignment::create([
            'restaurant_id' => $restaurant->id, 'hospital_admission_id' => $admission->id,
            'nurse_id' => $nurse->id, 'assigned_at' => '2026-09-15 09:00:00', 'status' => 'active', 'shift' => 'morning',
        ]);

        $this->actingAs($doctorUser)->post(route('manager.hospital-care-plans.store'), [
            'hospital_admission_id' => $admission->id, 'problem_diagnosis' => 'Risk of falls',
            'goal' => 'Patient remains safe', 'interventions' => 'Assist with transfers',
            'opened_at' => '2026-09-15 10:00:00',
        ])->assertSessionHasNoErrors();
        $plan = HospitalCarePlan::firstOrFail();

        $this->actingAs($nurse)->patch(route('manager.hospital-care-plans.update', $plan), [
            'nursing_progress' => 'Reviewed call bell and assisted mobility.',
            'nursing_status' => 'in_progress', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Risk of falls', $plan->fresh()->problem_diagnosis);
        $this->assertSame('Reviewed call bell and assisted mobility.', $plan->fresh()->nursing_progress);
        $this->assertSame(2, HospitalCarePlanEvent::count());
    }

    public function test_window_ordering_and_status_lifecycle_are_enforced_and_audited(): void
    {
        $restaurant = $this->restaurant(['hospital-care-plans']);
        $manager = $this->user($restaurant, ['role' => 'manager']);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'P-' . uniqid(), 'name' => 'Patient']);
        $admission = $this->admission($restaurant, $patient, null, '2026-09-15 10:00:00', '2026-09-15 14:00:00');

        $this->actingAs($manager)->post(route('manager.hospital-care-plans.store'), [
            'hospital_admission_id' => $admission->id, 'problem_diagnosis' => 'Pain',
            'goal' => 'Pain controlled', 'interventions' => 'Assess pain',
            'opened_at' => '2026-09-15 09:00:00', 'review_due_at' => '2026-09-15 08:00:00',
        ])->assertSessionHasErrors(['opened_at', 'review_due_at']);
        $this->assertCount(0, HospitalCarePlan::all());

        $this->actingAs($manager)->post(route('manager.hospital-care-plans.store'), [
            'hospital_admission_id' => $admission->id, 'problem_diagnosis' => 'Pain',
            'goal' => 'Pain controlled', 'interventions' => 'Assess pain',
            'opened_at' => '2026-09-15 10:00:00',
        ])->assertSessionHasNoErrors();
        $plan = HospitalCarePlan::firstOrFail();
        $this->actingAs($manager)->patch(route('manager.hospital-care-plans.update', $plan), [
            'problem_diagnosis' => 'Pain', 'goal' => 'Pain controlled', 'interventions' => 'Assess pain',
            'status' => 'resolved', 'opened_at' => '2026-09-15 10:00:00',
            'resolved_at' => '2026-09-15 13:00:00', 'correction_notes' => 'Resolution confirmed',
        ])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $plan->fresh()->status);
        $this->assertSame(['recorded', 'status_changed'], $plan->events()->oldest('id')->pluck('action')->all());

        $this->actingAs($manager)->patch(route('manager.hospital-care-plans.update', $plan), [
            'problem_diagnosis' => 'Pain', 'goal' => 'Pain controlled', 'interventions' => 'Assess pain',
            'status' => 'active', 'opened_at' => '2026-09-15 10:00:00', 'correction_notes' => 'Reopen',
        ])->assertStatus(422);
    }

    public function test_tenant_and_admission_ownership_cannot_be_changed(): void
    {
        $first = $this->restaurant(['hospital-care-plans']);
        $manager = $this->user($first, ['role' => 'manager']);
        $patient = Patient::create(['restaurant_id' => $first->id, 'patient_number' => 'P-' . uniqid(), 'name' => 'Patient']);
        $admission = $this->admission($first, $patient);
        $this->actingAs($manager)->post(route('manager.hospital-care-plans.store'), [
            'hospital_admission_id' => $admission->id, 'problem_diagnosis' => 'Observation',
            'goal' => 'Stable', 'interventions' => 'Monitor', 'opened_at' => '2026-09-15 10:00:00',
        ]);
        $plan = HospitalCarePlan::firstOrFail();
        $second = $this->restaurant(['hospital-care-plans']);
        $otherManager = $this->user($second, ['role' => 'manager']);
        $this->actingAs($otherManager)->patch(route('manager.hospital-care-plans.update', $plan), [
            'problem_diagnosis' => 'Cross tenant', 'goal' => 'No', 'interventions' => 'No',
            'status' => 'active', 'opened_at' => '2026-09-15 10:00:00', 'correction_notes' => 'No',
        ])->assertNotFound();
        $this->assertSame($first->id, $plan->fresh()->restaurant_id);
        $this->assertSame($admission->id, $plan->fresh()->hospital_admission_id);
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
