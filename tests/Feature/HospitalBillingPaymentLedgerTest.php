<?php

namespace Tests\Feature;

use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\HospitalPatientInsurance;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalBillingPaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $suffix = ''): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Ledger Hospital ' . $suffix,
            'slug' => 'ledger-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions', 'hospital-billing'],
        ]);
        $manager = User::create([
            'name' => 'Ledger Manager',
            'email' => uniqid('ledger-manager-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);
        $patient = Patient::create([
            'restaurant_id' => $restaurant->id,
            'patient_number' => 'PAT-' . strtoupper(substr(uniqid(), -8)),
            'name' => 'Ledger Patient',
        ]);
        $admission = HospitalAdmission::create([
            'restaurant_id' => $restaurant->id,
            'admission_number' => 'ADM-' . strtoupper(substr(uniqid(), -8)),
            'patient_id' => $patient->id,
            'status' => 'admitted',
            'admitted_at' => now()->subDay(),
        ]);
        $billing = HospitalBilling::create([
            'restaurant_id' => $restaurant->id,
            'patient_id' => $patient->id,
            'hospital_admission_id' => $admission->id,
            'charge_type' => 'bed',
            'description' => 'Ledger room',
            'quantity' => 1,
            'unit_amount' => 100,
            'status' => 'posted',
            'charged_at' => now()->subHour(),
            'posted_at' => now()->subHour(),
        ]);

        return [$restaurant, $manager, $admission, $billing];
    }

    public function test_partial_and_full_payments_settle_only_when_the_charge_is_full(): void
    {
        [, $manager, , $billing] = $this->fixture();

        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 40, 'method' => 'cash', 'reference' => 'DEP-1',
        ])->assertRedirect();
        $billing->refresh();
        $this->assertSame('posted', $billing->status);
        $this->assertSame(60.0, $billing->outstandingAmount());

        $this->actingAs($manager)->patch(route('manager.hospital-billing.pay', $billing), [
            'amount' => 60, 'payment_method' => 'card', 'payment_reference' => 'BAL-1',
        ])->assertRedirect();
        $billing->refresh();
        $this->assertSame('paid', $billing->status);
        $this->assertSame(100.0, $billing->settledAmount());
        $this->assertDatabaseCount('hospital_billing_payments', 2);
    }

    public function test_refund_reopens_a_paid_charge_and_cannot_exceed_settlement(): void
    {
        [, $manager, , $billing] = $this->fixture();
        $this->actingAs($manager)->patch(route('manager.hospital-billing.pay', $billing), [
            'payment_reference' => 'FULL-1',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('manager.hospital-billing.refunds.store', $billing), [
            'amount' => 25, 'reference' => 'REF-1',
        ])->assertRedirect();
        $billing->refresh();
        $this->assertSame('posted', $billing->status);
        $this->assertSame(75.0, $billing->settledAmount());
        $this->assertSame(25.0, $billing->outstandingAmount());

        $this->actingAs($manager)->post(route('manager.hospital-billing.refunds.store', $billing), [
            'amount' => 76, 'reference' => 'REF-2',
        ])->assertStatus(422);
    }

    public function test_retried_payment_reference_is_idempotent(): void
    {
        [, $manager, , $billing] = $this->fixture();
        $payload = ['amount' => 40, 'method' => 'cash', 'reference' => 'RETRY-1'];

        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), $payload)
            ->assertRedirect();
        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), $payload)
            ->assertRedirect()->assertSessionHas('success', 'This payment reference was already recorded; no duplicate payment was created.');

        $this->assertDatabaseCount('hospital_billing_payments', 1);
        $this->assertSame(40.0, $billing->fresh()->settledAmount());
    }

    public function test_retried_refund_reference_is_idempotent(): void
    {
        [, $manager, , $billing] = $this->fixture();
        $this->actingAs($manager)->patch(route('manager.hospital-billing.pay', $billing), [
            'payment_reference' => 'FULL-REFUND',
        ])->assertRedirect();
        $payload = ['amount' => 25, 'reference' => 'REFUND-RETRY'];

        $this->actingAs($manager)->post(route('manager.hospital-billing.refunds.store', $billing), $payload)
            ->assertRedirect();
        $this->actingAs($manager)->post(route('manager.hospital-billing.refunds.store', $billing), $payload)
            ->assertRedirect()->assertSessionHas('success', 'This refund reference was already recorded; no duplicate refund was created.');

        $this->assertDatabaseCount('hospital_billing_payments', 2);
        $this->assertSame(75.0, $billing->fresh()->settledAmount());
    }

    public function test_payment_overage_staff_action_and_tenant_boundary_are_rejected(): void
    {
        [$restaurant, $manager, , $billing] = $this->fixture('one');
        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 101, 'reference' => 'TOO-MUCH',
        ])->assertStatus(422);

        $staff = User::create([
            'name' => 'Ledger Staff', 'email' => uniqid('ledger-staff-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999), 'role' => 'staff',
            'restaurant_id' => $restaurant->id, 'password' => bcrypt('password'),
        ]);
        $this->actingAs($staff)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 10, 'reference' => 'STAFF',
        ])->assertForbidden();

        [, $otherManager, ,] = $this->fixture('two');
        $this->actingAs($otherManager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 10, 'reference' => 'CROSS-TENANT',
        ])->assertNotFound();
        $this->assertDatabaseHas('hospital_billings', ['id' => $billing->id, 'restaurant_id' => $restaurant->id]);
        $this->assertDatabaseCount('hospital_billing_payments', 0);
    }

    public function test_insurance_payments_require_an_explicit_policy_link(): void
    {
        [, $manager, $admission, $billing] = $this->fixture('insurance-policy');
        $policy = HospitalPatientInsurance::create([
            'restaurant_id' => $admission->restaurant_id,
            'patient_id' => $admission->patient_id,
            'payer' => 'Blue Cross',
            'policy_number' => 'POL-INS-1001',
            'member_number' => 'MEM-1001',
            'coverage_starts' => now()->subMonth(),
            'coverage_ends' => now()->addMonth(),
            'status' => 'active',
        ]);

        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 20,
            'method' => 'insurance',
            'reference' => 'INS-UNLINKED',
        ])->assertStatus(422);

        $billing->update(['hospital_patient_insurance_id' => $policy->id]);

        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 20,
            'method' => 'insurance',
            'reference' => 'INS-LINKED',
        ])->assertRedirect();

        $billing = $billing->fresh();
        $this->assertSame(20.0, $billing->settledAmount());
        $this->assertSame('posted', $billing->status);
        $this->assertSame(80.0, $billing->outstandingAmount());
    }

    public function test_insurance_refunds_are_rejected_until_the_residual_patient_ledger_is_used(): void
    {
        [, $manager, , $billing] = $this->fixture('insurance-refund');
        $policy = HospitalPatientInsurance::create([
            'restaurant_id' => $billing->restaurant_id,
            'patient_id' => $billing->patient_id,
            'payer' => 'Apex Care',
            'policy_number' => 'POL-INS-2001',
            'member_number' => 'MEM-2001',
            'coverage_starts' => now()->subMonth(),
            'coverage_ends' => now()->addMonth(),
            'status' => 'active',
        ]);
        $billing->update(['hospital_patient_insurance_id' => $policy->id]);

        $this->actingAs($manager)->patch(route('manager.hospital-billing.pay', $billing), [
            'payment_reference' => 'INS-REFUND-PAID',
            'payment_method' => 'insurance',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('manager.hospital-billing.refunds.store', $billing), [
            'amount' => 10,
            'reference' => 'INS-REFUND-DIRECT',
            'payment_method' => 'insurance',
        ])->assertStatus(422);
    }

    public function test_insurance_payment_follows_insurer_first_then_patient_residual_policy(): void
    {
        [, $manager, $admission, $billing] = $this->fixture('insurer-first');
        $policy = HospitalPatientInsurance::create([
            'restaurant_id' => $admission->restaurant_id,
            'patient_id' => $admission->patient_id,
            'payer' => 'Blue Shield',
            'policy_number' => 'POL-INS-2002',
            'member_number' => 'MEM-2002',
            'coverage_starts' => now()->subMonth(),
            'coverage_ends' => now()->addMonth(),
            'status' => 'active',
        ]);
        $claim = \App\Models\InsuranceClaim::create([
            'restaurant_id' => $admission->restaurant_id,
            'provider_id' => null,
            'hospital_patient_insurance_id' => $policy->id,
            'claim_number' => 'CLM-INS-2002',
            'claimed_amount' => 100,
            'approved_amount' => 80,
            'status' => 'approved',
        ]);
        $billing->update([
            'hospital_patient_insurance_id' => $policy->id,
            'insurance_claim_id' => $claim->id,
        ]);

        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 90,
            'method' => 'insurance',
            'reference' => 'INS-OVER',
        ])->assertStatus(422);

        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 80,
            'method' => 'insurance',
            'reference' => 'INS-PRIMARY',
        ])->assertRedirect();

        $this->assertSame(80.0, $billing->fresh()->settledAmount());
        $this->assertSame(20.0, $billing->fresh()->remainingPatientBalance());

        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 21,
            'method' => 'card',
            'reference' => 'PATIENT-RESIDUAL',
        ])->assertStatus(422);

        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 20,
            'method' => 'card',
            'reference' => 'PATIENT-RESIDUAL-OK',
        ])->assertRedirect();

        $billing = $billing->fresh();
        $this->assertSame(100.0, $billing->settledAmount());
        $this->assertSame('paid', $billing->status);
        $this->assertSame('insurance_first_then_patient', $billing->settlementPrecedence()['precedence']);
    }

    public function test_partial_settlement_still_blocks_discharge(): void
    {
        [, $manager, $admission, $billing] = $this->fixture();
        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $billing), [
            'amount' => 50, 'reference' => 'PARTIAL',
        ])->assertRedirect();

        $this->actingAs($manager)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable',
            'discharged_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('billing');
    }

    public function test_mixed_inpatient_charges_must_all_be_settled_before_discharge(): void
    {
        [, $manager, $admission, $bedCharge] = $this->fixture('mixed');
        $charges = [
            $bedCharge,
            HospitalBilling::create([
                'restaurant_id' => $admission->restaurant_id,
                'patient_id' => $admission->patient_id,
                'hospital_admission_id' => $admission->id,
                'charge_type' => 'medication',
                'description' => 'Dispensed medication',
                'quantity' => 2,
                'unit_amount' => 25,
                'status' => 'posted',
                'charged_at' => now()->subHour(),
                'posted_at' => now()->subHour(),
            ]),
            HospitalBilling::create([
                'restaurant_id' => $admission->restaurant_id,
                'patient_id' => $admission->patient_id,
                'hospital_admission_id' => $admission->id,
                'charge_type' => 'laboratory',
                'description' => 'Inpatient laboratory panel',
                'quantity' => 1,
                'unit_amount' => 75,
                'status' => 'posted',
                'charged_at' => now()->subHour(),
                'posted_at' => now()->subHour(),
            ]),
            HospitalBilling::create([
                'restaurant_id' => $admission->restaurant_id,
                'patient_id' => $admission->patient_id,
                'hospital_admission_id' => $admission->id,
                'charge_type' => 'surgery',
                'description' => 'Operating theatre service',
                'quantity' => 1,
                'unit_amount' => 200,
                'status' => 'posted',
                'charged_at' => now()->subHour(),
                'posted_at' => now()->subHour(),
            ]),
        ];

        $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $charges[0]), [
            'amount' => 50,
            'method' => 'cash',
            'reference' => 'MIXED-PARTIAL',
        ])->assertRedirect();

        $this->actingAs($manager)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable',
            'discharged_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('billing');
        $this->assertSame('admitted', $admission->fresh()->status);

        foreach ($charges as $index => $charge) {
            if ($index === 0) {
                $remaining = $charge->fresh()->outstandingAmount();
                $this->actingAs($manager)->post(route('manager.hospital-billing.payments.store', $charge), [
                    'amount' => $remaining,
                    'method' => 'card',
                    'reference' => 'MIXED-BALANCE',
                ])->assertRedirect();
                continue;
            }

            $this->actingAs($manager)->patch(route('manager.hospital-billing.pay', $charge), [
                'payment_method' => 'card',
                'payment_reference' => 'MIXED-' . $index,
            ])->assertRedirect();
        }

        $this->actingAs($manager)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable',
            'discharged_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('discharged', $admission->fresh()->status);
        $this->assertSame(4, HospitalBilling::where('hospital_admission_id', $admission->id)->where('status', 'paid')->count());
        $this->assertSame(4, HospitalBilling::where('hospital_admission_id', $admission->id)->count());
    }
}
