<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\DischargeSummary;
use App\Models\DischargeSummaryEvent;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalAdmissionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admissionWithManager(): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Professional Hospital',
            'slug' => 'billing-reconciliation-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions', 'hospital-billing'],
        ]);
        $user = User::create([
            'name' => 'Manager',
            'email' => uniqid('admission-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);
        $patient = Patient::create([
            'restaurant_id' => $restaurant->id,
            'patient_number' => 'PAT-' . strtoupper(substr(uniqid(), -8)),
            'name' => 'Billing Patient',
        ]);
        $admission = HospitalAdmission::create([
            'restaurant_id' => $restaurant->id,
            'admission_number' => 'ADM-' . strtoupper(substr(uniqid(), -8)),
            'patient_id' => $patient->id,
            'status' => 'admitted',
            'admitted_at' => now()->subDay(),
        ]);

        return [$user, $admission];
    }

    private function charge(HospitalAdmission $admission, string $status): HospitalBilling
    {
        return HospitalBilling::create([
            'restaurant_id' => $admission->restaurant_id,
            'patient_id' => $admission->patient_id,
            'hospital_admission_id' => $admission->id,
            'charge_type' => 'bed',
            'description' => 'Room charge',
            'quantity' => 1,
            'unit_amount' => 100,
            'status' => $status,
            'charged_at' => now()->subHour(),
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    public function test_admission_routes_require_the_hospital_admissions_module(): void
    {
        $restaurant = Restaurant::create(['name' => 'Clinic Tenant', 'slug' => 'clinic-tenant', 'status' => 'active', 'enabled_modules' => ['medical']]);
        $user = User::create(['name' => 'Manager', 'email' => 'admission-block@example.com', 'phone' => '03000000009', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);

        $this->actingAs($user)->get(route('manager.admissions.index'))->assertForbidden();
        $this->actingAs($user)->get(route('manager.admissions.create'))->assertForbidden();
    }

    public function test_unpaid_posted_charges_block_discharge(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $this->charge($admission, 'posted');

        $this->actingAs($user)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable',
            'discharged_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasErrors('billing');

        $this->assertDatabaseHas('hospital_admissions', ['id' => $admission->id, 'status' => 'admitted']);
    }

    public function test_paid_charges_allow_discharge(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $this->charge($admission, 'paid');

        $this->actingAs($user)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable',
            'discharged_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('hospital_admissions', ['id' => $admission->id, 'status' => 'discharged']);
    }

    public function test_draft_and_void_charges_allow_discharge(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $this->charge($admission, 'draft');
        $this->charge($admission, 'void');

        $this->actingAs($user)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable',
            'discharged_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('hospital_admissions', ['id' => $admission->id, 'status' => 'discharged']);
    }

    public function test_discharge_reconciles_draft_bed_charge_to_billable_stay_days(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $admission->update([
            'admitted_at' => now()->subDays(3)->subHours(2),
            'bed_daily_rate' => 175,
        ]);
        $charge = HospitalBilling::create([
            'restaurant_id' => $admission->restaurant_id,
            'patient_id' => $admission->patient_id,
            'hospital_admission_id' => $admission->id,
            'charge_type' => 'bed',
            'description' => 'Bed accommodation - Private',
            'quantity' => 1,
            'unit_amount' => 175,
            'status' => 'draft',
            'charged_at' => $admission->admitted_at,
        ]);

        $this->actingAs($user)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable',
            'discharged_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $charge->refresh();
        $this->assertSame(4.0, (float) $charge->quantity);
        $this->assertSame(700.0, (float) $charge->total_amount);
        $this->assertSame(1, HospitalBilling::where('hospital_admission_id', $admission->id)->where('charge_type', 'bed')->count());
    }

    public function test_discharge_records_initial_audit_event(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $this->actingAs($user)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable',
            'discharged_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $summary = DischargeSummary::firstOrFail();
        $this->assertDatabaseHas('discharge_summary_events', [
            'discharge_summary_id' => $summary->id,
            'action' => 'recorded',
            'changed_by' => $user->id,
        ]);
        $this->assertSame('Stable', DischargeSummaryEvent::firstOrFail()->new_snapshot['discharge_diagnosis']);
    }

    public function test_manager_can_correct_summary_with_reason_without_changing_timestamps(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $dischargedAt = now()->subHour()->format('Y-m-d H:i:s');
        $this->actingAs($user)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable', 'discharged_at' => $dischargedAt,
        ]);
        $summary = DischargeSummary::firstOrFail();
        $admissionTimestamp = $admission->fresh()->discharged_at;

        $this->actingAs($user)->patch(route('manager.admissions.discharge-summary.update', $summary), [
            'discharge_diagnosis' => 'Improved',
            'correction_notes' => 'Corrected diagnosis after physician review.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Improved', $summary->fresh()->discharge_diagnosis);
        $this->assertEquals($admissionTimestamp, $admission->fresh()->discharged_at);
        $this->assertDatabaseHas('discharge_summary_events', ['action' => 'edited', 'correction_reason' => 'Corrected diagnosis after physician review.']);
    }

    public function test_correction_rejects_unauthorized_cross_tenant_and_noop_requests(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $this->actingAs($user)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable', 'discharged_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $summary = DischargeSummary::firstOrFail();
        $this->actingAs($user)->patch(route('manager.admissions.discharge-summary.update', $summary), [
            'discharge_diagnosis' => 'Stable', 'correction_notes' => 'No actual change.',
        ])->assertSessionHasErrors('correction_notes');

        $staff = User::create(['name' => 'Staff', 'email' => uniqid('staff-').'@example.com', 'phone' => '03000'.random_int(100000, 999999), 'role' => 'staff', 'restaurant_id' => $user->restaurant_id, 'password' => bcrypt('password')]);
        $this->actingAs($staff)->patch(route('manager.admissions.discharge-summary.update', $summary), [
            'discharge_diagnosis' => 'Changed', 'correction_notes' => 'Attempt.',
        ])->assertForbidden();

        $other = Restaurant::create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'status' => 'active', 'enabled_modules' => ['hospital-admissions']]);
        $owner = User::create(['name' => 'Other Manager', 'email' => uniqid('other-').'@example.com', 'phone' => '03000'.random_int(100000, 999999), 'role' => 'manager', 'restaurant_id' => $other->id, 'password' => bcrypt('password')]);
        $this->actingAs($owner)->patch(route('manager.admissions.discharge-summary.update', $summary), [
            'discharge_diagnosis' => 'Changed', 'correction_notes' => 'Attempt.',
        ])->assertNotFound();
    }

    public function test_hospital_billing_summary_tracks_draft_posted_paid_void_totals(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $this->charge($admission, 'draft');
        $this->charge($admission, 'posted');
        $this->charge($admission, 'paid');
        $this->charge($admission, 'void');

        $summary = $admission->fresh()->billingSummary();

        $this->assertSame(400.0, round($summary['gross'], 2));
        $this->assertSame(100.0, round($summary['posted'], 2));
        $this->assertSame(100.0, round($summary['paid'], 2));
        $this->assertSame(100.0, round($summary['outstanding'], 2));
        $this->assertSame(100.0, round($summary['draft'], 2));
        $this->assertSame(100.0, round($summary['void'], 2));
        $this->assertSame(400.0, round(HospitalBilling::tenantSummary($admission->restaurant_id)['gross'], 2));
    }

    public function test_marking_posted_charge_paid_requires_payment_reference(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $billing = $this->charge($admission, 'posted');

        $this->actingAs($user)->patch(route('manager.hospital-billing.pay', $billing), [])->assertSessionHasErrors('payment_reference');

        $this->actingAs($user)->patch(route('manager.hospital-billing.pay', $billing), [
            'payment_reference' => 'PAY-REF-001',
            'payment_notes' => 'Settled by cash.',
        ])->assertRedirect();

        $this->assertSame('paid', $billing->fresh()->status);
        $this->assertSame('PAY-REF-001', $billing->fresh()->payment_reference);
        $this->assertSame('Settled by cash.', $billing->fresh()->payment_notes);
    }

    public function test_paid_and_void_transitions_are_restricted_and_cross_tenant_isolation_is_enforced(): void
    {
        [$user, $admission] = $this->admissionWithManager();
        $billing = $this->charge($admission, 'posted');

        $this->actingAs($user)->patch(route('manager.hospital-billing.pay', $billing), [
            'payment_reference' => 'PAY-REF-002',
        ])->assertRedirect();

        $this->actingAs($user)->patch(route('manager.hospital-billing.pay', $billing), [
            'payment_reference' => 'PAY-REF-003',
        ])->assertStatus(422);

        $this->actingAs($user)->patch(route('manager.hospital-billing.void', $billing), [])->assertStatus(422);

        $other = Restaurant::create([
            'name' => 'Other Hospital',
            'slug' => 'other-hospital-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions', 'hospital-billing'],
        ]);
        $otherUser = User::create([
            'name' => 'Other Manager',
            'email' => uniqid('other-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'manager',
            'restaurant_id' => $other->id,
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($otherUser)->patch(route('manager.hospital-billing.pay', $billing), [
            'payment_reference' => 'PAY-REF-OTHER',
        ])->assertNotFound();
    }
}
