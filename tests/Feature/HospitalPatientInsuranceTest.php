<?php

namespace Tests\Feature;

use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\HospitalPatientInsurance;
use App\Models\InsuranceClaim;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalPatientInsuranceTest extends TestCase
{
    use RefreshDatabase;

    private function hospital(string $suffix = ''): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Insurance Hospital ' . $suffix,
            'slug' => 'insurance-hospital-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions', 'hospital-billing'],
        ]);
        $manager = User::create([
            'name' => 'Insurance Manager',
            'email' => uniqid('insurance-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);
        $patient = Patient::create([
            'restaurant_id' => $restaurant->id,
            'patient_number' => 'PAT-' . strtoupper(substr(uniqid(), -8)),
            'name' => 'Insured Patient',
        ]);

        return [$restaurant, $manager, $patient];
    }

    private function admission(Patient $patient): HospitalAdmission
    {
        return HospitalAdmission::create([
            'restaurant_id' => $patient->restaurant_id,
            'admission_number' => 'ADM-' . strtoupper(substr(uniqid(), -8)),
            'patient_id' => $patient->id,
            'status' => 'admitted',
            'admitted_at' => now()->subHour(),
        ]);
    }

    private function policy(Patient $patient, string $member = 'MEM-1'): HospitalPatientInsurance
    {
        return HospitalPatientInsurance::create([
            'restaurant_id' => $patient->restaurant_id,
            'patient_id' => $patient->id,
            'payer' => 'Acme Health',
            'policy_number' => 'POL-1',
            'member_number' => $member,
            'status' => 'active',
        ]);
    }

    public function test_manager_can_create_policy_and_link_it_to_an_admission(): void
    {
        [, $manager, $patient] = $this->hospital();
        $admission = $this->admission($patient);

        $this->actingAs($manager)->post(route('manager.hospital-billing.insurance.store'), [
            'patient_id' => $patient->id,
            'payer' => 'Acme Health',
            'policy_number' => 'POL-1',
            'member_number' => 'MEM-1',
            'coverage_starts' => now()->subMonth()->toDateString(),
            'coverage_ends' => now()->addYear()->toDateString(),
            'status' => 'active',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $policy = HospitalPatientInsurance::firstOrFail();
        $this->actingAs($manager)->patch(route('manager.hospital-billing.insurance.admission', $admission), [
            'hospital_patient_insurance_id' => $policy->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($policy->id, $admission->fresh()->hospital_patient_insurance_id);
    }

    public function test_dates_and_status_are_validated(): void
    {
        [, $manager, $patient] = $this->hospital();

        $this->actingAs($manager)->post(route('manager.hospital-billing.insurance.store'), [
            'patient_id' => $patient->id,
            'payer' => 'Acme Health',
            'coverage_starts' => '2026-09-20',
            'coverage_ends' => '2026-09-19',
            'status' => 'unknown',
        ])->assertSessionHasErrors(['coverage_ends', 'status']);
    }

    public function test_policy_linkage_rejects_another_patients_policy_and_billing_keeps_reconciliation(): void
    {
        [, $manager, $patient] = $this->hospital('one');
        [, , $otherPatient] = $this->hospital('two');
        $admission = $this->admission($patient);
        $otherPolicy = $this->policy($otherPatient, 'MEM-OTHER');

        $this->actingAs($manager)->patch(route('manager.hospital-billing.insurance.admission', $admission), [
            'hospital_patient_insurance_id' => $otherPolicy->id,
        ])->assertSessionHasErrors('hospital_patient_insurance_id');

        $billing = HospitalBilling::create([
            'restaurant_id' => $patient->restaurant_id,
            'patient_id' => $patient->id,
            'hospital_admission_id' => $admission->id,
            'charge_type' => 'bed',
            'description' => 'Room',
            'quantity' => 1,
            'unit_amount' => 100,
            'status' => 'posted',
            'charged_at' => now(),
        ]);
        $this->actingAs($manager)->patch(route('manager.hospital-billing.insurance-claim', $billing), [
            'hospital_patient_insurance_id' => $otherPolicy->id,
        ])->assertSessionHasErrors('hospital_patient_insurance_id');

        $this->assertSame(100.0, (float) $admission->fresh()->billingSummary()['outstanding']);
        $this->assertNull($billing->fresh()->hospital_patient_insurance_id);
    }

    public function test_claim_with_an_explicit_hospital_profile_must_match_the_admission_patient(): void
    {
        [, $manager, $patient] = $this->hospital('claim-one');
        $otherPatient = Patient::create([
            'restaurant_id' => $patient->restaurant_id,
            'patient_number' => 'PAT-' . strtoupper(substr(uniqid(), -8)),
            'name' => 'Other Insured Patient',
        ]);
        $admission = $this->admission($patient);
        $otherPolicy = $this->policy($otherPatient, 'MEM-CLAIM-OTHER');
        $claim = InsuranceClaim::create([
            'restaurant_id' => $patient->restaurant_id,
            'hospital_patient_insurance_id' => $otherPolicy->id,
            'claim_number' => 'CLM-WRONG-PATIENT',
            'claimed_amount' => 100,
            'status' => 'submitted',
        ]);
        $billing = HospitalBilling::create([
            'restaurant_id' => $patient->restaurant_id,
            'patient_id' => $patient->id,
            'hospital_admission_id' => $admission->id,
            'charge_type' => 'bed',
            'description' => 'Room',
            'quantity' => 1,
            'unit_amount' => 100,
            'status' => 'draft',
            'charged_at' => now(),
        ]);

        $this->actingAs($manager)->patch(route('manager.hospital-billing.insurance-claim', $billing), [
            'insurance_claim_id' => $claim->id,
        ])->assertStatus(422);
        $this->assertNull($billing->fresh()->insurance_claim_id);
    }

    public function test_non_manager_cannot_manage_patient_insurance(): void
    {
        [$restaurant, , $patient] = $this->hospital();
        $staff = User::create([
            'name' => 'Nurse',
            'email' => uniqid('nurse-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'staff',
            'staff_type' => 'nurse',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($staff)->get(route('manager.hospital-billing.insurance.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('manager.hospital-billing.insurance.store'), [
            'patient_id' => $patient->id,
            'payer' => 'Acme Health',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_policy_records_are_tenant_isolated(): void
    {
        [, $manager, $patient] = $this->hospital('one');
        [, $otherManager, $otherPatient] = $this->hospital('two');
        $policy = $this->policy($patient);

        $this->actingAs($otherManager)->get(route('manager.hospital-billing.insurance.index'))
            ->assertOk()->assertDontSee($policy->member_number);
        $this->actingAs($otherManager)->patch(route('manager.hospital-billing.insurance.update', $policy), [
            'patient_id' => $otherPatient->id,
            'payer' => 'Other',
            'status' => 'active',
        ])->assertNotFound();
    }
}
