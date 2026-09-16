<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\BedTransfer;
use App\Models\Department;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\HospitalBedStayPeriod;
use App\Models\HospitalTransfer;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalBedTransferTest extends TestCase
{
    use RefreshDatabase;

    private function setUpManagerWithAdmission(): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Professional Hospital',
            'slug' => 'professional-hospital-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions'],
        ]);
        $manager = User::create([
            'name' => 'Manager',
            'email' => uniqid('manager-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);
        $patient = Patient::create([
            'restaurant_id' => $restaurant->id,
            'patient_number' => 'PAT-' . strtoupper(substr(uniqid(), -8)),
            'name' => 'Test Patient',
        ]);
        $department = Department::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Ward 1',
            'code' => 'W1-' . uniqid(),
            'is_active' => true,
        ]);
        $fromWard = Ward::create(['restaurant_id' => $restaurant->id, 'name' => 'Medical Ward', 'code' => 'MW-' . uniqid(), 'is_active' => true]);
        $toWard = Ward::create(['restaurant_id' => $restaurant->id, 'name' => 'Surgical Ward', 'code' => 'SW-' . uniqid(), 'is_active' => true]);
        $fromBed = Bed::create(['restaurant_id' => $restaurant->id, 'ward_id' => $fromWard->id, 'name' => 'Bed A', 'code' => 'A-' . uniqid(), 'status' => 'occupied']);
        $toBed = Bed::create(['restaurant_id' => $restaurant->id, 'ward_id' => $toWard->id, 'name' => 'Bed B', 'code' => 'B-' . uniqid(), 'status' => 'available']);
        $admission = HospitalAdmission::create([
            'restaurant_id' => $restaurant->id,
            'admission_number' => 'ADM-' . strtoupper(substr(uniqid(), -8)),
            'patient_id' => $patient->id,
            'department_id' => $department->id,
            'bed_id' => $fromBed->id,
            'status' => 'admitted',
            'admitted_at' => now()->subDay(),
        ]);

        return [$manager, $admission, $fromBed, $toBed, $department, $fromWard, $toWard];
    }

    public function test_manager_can_create_bed_transfer_history_when_bed_changes(): void
    {
        [$manager, $admission, $fromBed, $toBed] = $this->setUpManagerWithAdmission();

        $this->actingAs($manager)->patch(route('manager.admissions.transfer', $admission), [
            'department_id' => $admission->department_id,
            'bed_id' => $toBed->id,
            'transferred_at' => now()->format('Y-m-d H:i:s'),
            'reason' => 'Bed relocation for monitoring',
            'notes' => 'Move for ICU observation.',
        ])->assertRedirect();

        $this->assertDatabaseHas('bed_transfers', [
            'hospital_admission_id' => $admission->id,
            'patient_id' => $admission->patient_id,
            'previous_bed_id' => $fromBed->id,
            'new_bed_id' => $toBed->id,
            'requested_by' => $manager->id,
            'approved_by' => $manager->id,
            'reason' => 'Bed relocation for monitoring',
        ]);
        $this->assertDatabaseHas('hospital_transfers', [
            'hospital_admission_id' => $admission->id,
            'from_bed_id' => $fromBed->id,
            'to_bed_id' => $toBed->id,
            'requested_by' => $manager->id,
        ]);
        $this->assertSame('available', $fromBed->fresh()->status);
        $this->assertSame('occupied', $toBed->fresh()->status);
        $this->assertSame($toBed->id, $admission->fresh()->bed_id);
        $this->assertInstanceOf(BedTransfer::class, $admission->fresh()->bedTransfers()->first());
    }

    public function test_releasing_a_bed_records_a_release_transfer_and_marks_it_available(): void
    {
        [$manager, $admission, $fromBed] = $this->setUpManagerWithAdmission();

        $this->actingAs($manager)->patch(route('manager.admissions.transfer', $admission), [
            'department_id' => $admission->department_id,
            'bed_id' => '',
            'transferred_at' => now()->format('Y-m-d H:i:s'),
            'reason' => 'Bed released for cleaning',
        ])->assertRedirect();

        $this->assertDatabaseHas('bed_transfers', [
            'hospital_admission_id' => $admission->id,
            'previous_bed_id' => $fromBed->id,
            'new_bed_id' => null,
            'reason' => 'Bed released for cleaning',
        ]);
        $this->assertSame('available', $fromBed->fresh()->status);
        $this->assertNull($admission->fresh()->bed_id);
    }

    public function test_transfer_closes_previous_rate_period_and_discharge_reconciles_both_periods(): void
    {
        [$manager, $admission, $fromBed, $toBed, , $fromWard, $toWard] = $this->setUpManagerWithAdmission();
        $fromWard->update(['room_category' => 'Standard', 'daily_rate' => 100]);
        $toWard->update(['room_category' => 'Private', 'daily_rate' => 300]);
        $admittedAt = now()->subDays(3);
        $admission->update(['admitted_at' => $admittedAt, 'bed_daily_rate' => 100]);
        $initialCharge = HospitalBilling::create([
            'restaurant_id' => $admission->restaurant_id, 'patient_id' => $admission->patient_id,
            'hospital_admission_id' => $admission->id, 'charge_type' => 'bed',
            'description' => 'Bed accommodation - Standard', 'quantity' => 1, 'unit_amount' => 100,
            'status' => 'draft', 'charged_at' => $admittedAt,
        ]);
        HospitalBedStayPeriod::create([
            'restaurant_id' => $admission->restaurant_id, 'hospital_admission_id' => $admission->id,
            'bed_id' => $fromBed->id, 'ward_id' => $fromWard->id, 'started_at' => $admittedAt,
            'daily_rate' => 100, 'hospital_billing_id' => $initialCharge->id,
        ]);
        $transferredAt = now()->subDay();
        $this->actingAs($manager)->patch(route('manager.admissions.transfer', $admission), [
            'department_id' => $admission->department_id, 'bed_id' => $toBed->id,
            'transferred_at' => $transferredAt->format('Y-m-d H:i:s'), 'reason' => 'Private room requested',
        ])->assertRedirect();

        $this->assertDatabaseHas('hospital_bed_stay_periods', [
            'hospital_admission_id' => $admission->id, 'bed_id' => $fromBed->id,
            'ended_at' => $transferredAt->format('Y-m-d H:i:s'),
        ]);
        $this->assertSame(2, HospitalBedStayPeriod::where('hospital_admission_id', $admission->id)->count());

        $dischargedAt = now();
        $this->actingAs($manager)->patch(route('manager.admissions.discharge', $admission), [
            'discharge_diagnosis' => 'Stable', 'discharged_at' => $dischargedAt->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([100.0, 300.0], HospitalBilling::where('hospital_admission_id', $admission->id)
            ->where('charge_type', 'bed')->orderBy('unit_amount')->pluck('unit_amount')->map(fn ($value) => (float) $value)->all());
        $this->assertSame(2, HospitalBilling::where('hospital_admission_id', $admission->id)->where('charge_type', 'bed')->count());
    }

    public function test_transfer_rules_reject_duplicate_cross_tenant_and_timestamp_requests(): void
    {
        [$manager, $admission, $fromBed, $toBed] = $this->setUpManagerWithAdmission();

        $this->actingAs($manager)->patch(route('manager.admissions.transfer', $admission), [
            'department_id' => $admission->department_id,
            'bed_id' => $toBed->id,
            'transferred_at' => now()->format('Y-m-d H:i:s'),
            'reason' => 'For observation',
        ])->assertRedirect();

        $this->actingAs($manager)->patch(route('manager.admissions.transfer', $admission), [
            'department_id' => $admission->department_id,
            'bed_id' => $toBed->id,
            'transferred_at' => now()->format('Y-m-d H:i:s'),
            'reason' => 'Duplicate attempt',
        ])->assertSessionHasErrors('bed_id');

        $otherRestaurant = Restaurant::create([
            'name' => 'Other Hospital',
            'slug' => 'other-hospital-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions'],
        ]);
        $otherManager = User::create([
            'name' => 'Other Manager',
            'email' => uniqid('other-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'manager',
            'restaurant_id' => $otherRestaurant->id,
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($otherManager)->patch(route('manager.admissions.transfer', $admission), [
            'department_id' => $admission->department_id,
            'bed_id' => $fromBed->id,
            'transferred_at' => now()->format('Y-m-d H:i:s'),
            'reason' => 'Cross tenant',
        ])->assertNotFound();

        $this->actingAs($manager)->patch(route('manager.admissions.transfer', $admission), [
            'department_id' => $admission->department_id,
            'bed_id' => $fromBed->id,
            'transferred_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'reason' => 'Timestamp before admission',
        ])->assertSessionHasErrors('transferred_at');

        $this->assertSame(1, BedTransfer::count());
        $this->assertSame(1, HospitalTransfer::count());
    }
}
