<?php

namespace Tests\Feature;

use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\InsuranceClaim;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalBillingInsuranceReportTest extends TestCase
{
    use RefreshDatabase;

    private function setupHospital(string $suffix = ''): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Report Hospital ' . $suffix,
            'slug' => 'report-hospital-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions', 'hospital-billing'],
        ]);
        $manager = User::create([
            'name' => 'Report Manager',
            'email' => uniqid('report-manager-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);
        $patient = Patient::create([
            'restaurant_id' => $restaurant->id,
            'patient_number' => 'PAT-' . strtoupper(substr(uniqid(), -8)),
            'name' => 'Report Patient',
        ]);
        $admission = HospitalAdmission::create([
            'restaurant_id' => $restaurant->id,
            'admission_number' => 'ADM-' . strtoupper(substr(uniqid(), -8)),
            'patient_id' => $patient->id,
            'status' => 'admitted',
            'admitted_at' => now()->subDay(),
        ]);

        return [$restaurant, $manager, $admission];
    }

    private function charge(HospitalAdmission $admission, string $status, float $amount, ?int $claimId = null, string $type = 'bed'): HospitalBilling
    {
        return HospitalBilling::create([
            'restaurant_id' => $admission->restaurant_id,
            'patient_id' => $admission->patient_id,
            'hospital_admission_id' => $admission->id,
            'insurance_claim_id' => $claimId,
            'charge_type' => $type,
            'description' => ucfirst($type) . ' charge',
            'quantity' => 1,
            'unit_amount' => $amount,
            'status' => $status,
            'payment_method' => $status === 'paid' ? 'card' : null,
            'charged_at' => now()->subHour(),
        ]);
    }

    public function test_manager_can_link_existing_claim_and_report_insurance_totals(): void
    {
        [, $manager, $admission] = $this->setupHospital();
        $claim = InsuranceClaim::create([
            'restaurant_id' => $manager->restaurant_id,
            'claim_number' => 'CLM-REPORT',
            'claimed_amount' => 300,
            'status' => 'submitted',
        ]);
        $linkedPosted = $this->charge($admission, 'posted', 300, $claim->id, 'laboratory');
        $this->charge($admission, 'paid', 100, null, 'bed');
        $this->charge($admission, 'void', 50, $claim->id, 'radiology');

        $this->actingAs($manager)
            ->get(route('manager.hospital-billing.report'))
            ->assertOk()
            ->assertSee('Insurance linked')
            ->assertSee('Insurance outstanding')
            ->assertSee('300.00')
            ->assertSee('Totals by charge type')
            ->assertSee('laboratory');

        $this->actingAs($manager)
            ->get(route('manager.hospital-billing.report.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=hospital-billing-report.csv');

        $this->actingAs($manager)
            ->patch(route('manager.hospital-billing.insurance-claim', $linkedPosted), [
                'insurance_claim_id' => null,
            ])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($linkedPosted->fresh()->insurance_claim_id);
    }

    public function test_claim_linkage_rejects_a_claim_from_another_tenant(): void
    {
        [, $manager, $admission] = $this->setupHospital('one');
        [, , $otherAdmission] = $this->setupHospital('two');
        $otherClaim = InsuranceClaim::create([
            'restaurant_id' => $otherAdmission->restaurant_id,
            'claim_number' => 'CLM-OTHER',
            'claimed_amount' => 100,
            'status' => 'draft',
        ]);
        $billing = $this->charge($admission, 'draft', 100);

        $this->actingAs($manager)
            ->patch(route('manager.hospital-billing.insurance-claim', $billing), [
                'insurance_claim_id' => $otherClaim->id,
            ])
            ->assertSessionHasErrors('insurance_claim_id');

        $this->assertNull($billing->fresh()->insurance_claim_id);
    }

    public function test_linked_posted_charge_still_blocks_discharge_and_paid_charge_allows_it(): void
    {
        [, $manager, $admission] = $this->setupHospital('discharge');
        $claim = InsuranceClaim::create([
            'restaurant_id' => $manager->restaurant_id,
            'claim_number' => 'CLM-DISCHARGE',
            'claimed_amount' => 250,
            'status' => 'approved',
        ]);
        $billing = $this->charge($admission, 'posted', 250, $claim->id);
        $payload = [
            'discharge_diagnosis' => 'Stable',
            'discharged_at' => now()->format('Y-m-d H:i:s'),
        ];

        $this->actingAs($manager)
            ->patch(route('manager.admissions.discharge', $admission), $payload)
            ->assertSessionHasErrors('billing');
        $this->assertSame('admitted', $admission->fresh()->status);

        $this->actingAs($manager)
            ->patch(route('manager.hospital-billing.pay', $billing), [
                'payment_reference' => 'INS-PAID',
                'payment_method' => 'insurance',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->patch(route('manager.admissions.discharge', $admission), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('discharged', $admission->fresh()->status);
    }

    public function test_report_includes_department_grouping(): void
    {
        [, $manager, $admission] = $this->setupHospital('dept');

        // create a department and attach to the admission
        $dept = \App\Models\Department::create([
            'restaurant_id' => $admission->restaurant_id,
            'name' => 'Cardiology',
            'code' => 'CARD',
            'is_active' => true,
        ]);
        $admission->department_id = $dept->id;
        $admission->save();

        $this->charge($admission, 'paid', 200, null, 'bed');
        $this->charge($admission, 'posted', 150, null, 'laboratory');

        $this->actingAs($manager)
            ->get(route('manager.hospital-billing.report'))
            ->assertOk()
            ->assertSee('Totals by department/service')
            ->assertSee('Cardiology');
    }
}
