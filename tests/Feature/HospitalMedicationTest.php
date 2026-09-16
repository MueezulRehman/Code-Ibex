<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalMedicationAdministration;
use App\Models\HospitalMedicationFulfillment;
use App\Models\HospitalMedicationOrder;
use App\Models\MedicineBatch;
use App\Models\Medicine;
use App\Models\NursingAssignment;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalMedicationTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Medication Hospital',
            'slug' => 'medication-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-medication'],
        ]);
        $manager = User::create([
            'name' => 'Owner',
            'email' => uniqid('med-owner-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);
        $patient = Patient::create([
            'restaurant_id' => $restaurant->id,
            'patient_number' => 'PAT-' . strtoupper(substr(uniqid(), -8)),
            'name' => 'Inpatient',
        ]);
        $admission = HospitalAdmission::create([
            'restaurant_id' => $restaurant->id,
            'admission_number' => 'ADM-' . strtoupper(substr(uniqid(), -8)),
            'patient_id' => $patient->id,
            'status' => 'admitted',
            'admitted_at' => now()->subDay(),
        ]);
        $medicine = Medicine::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Test Paracetamol',
            'strength' => '500 mg',
            'track_stock' => true,
        ]);

        return [$restaurant, $manager, $patient, $admission, $medicine];
    }

    private function orderPayload(HospitalAdmission $admission, Medicine $medicine): array
    {
        return [
            'hospital_admission_id' => $admission->id,
            'medicine_id' => $medicine->id,
            'dose' => '500 mg',
            'route' => 'oral',
            'frequency' => 'once',
            'scheduled_start_at' => now()->subHour()->format('Y-m-d H:i:s'),
        ];
    }

    public function test_module_gate_blocks_tenants_without_hospital_medication(): void
    {
        $restaurant = Restaurant::create(['name' => 'Clinic', 'slug' => 'clinic-' . uniqid(), 'status' => 'active', 'enabled_modules' => ['medical']]);
        $user = User::create([
            'name' => 'Manager', 'email' => uniqid('blocked-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999), 'role' => 'manager',
            'restaurant_id' => $restaurant->id, 'password' => bcrypt('password'),
        ]);

        $this->actingAs($user)->get(route('manager.hospital-medication.index'))->assertForbidden();
    }

    public function test_manager_can_create_order_and_assigned_nurse_can_record_lifecycle(): void
    {
        [, $manager, , $admission, $medicine] = $this->context();
        $this->actingAs($manager)->post(route('manager.hospital-medication.orders.store'), $this->orderPayload($admission, $medicine))
            ->assertRedirect()->assertSessionHasNoErrors();

        $order = HospitalMedicationOrder::firstOrFail();
        $nurse = User::create([
            'name' => 'Assigned Nurse', 'email' => uniqid('nurse-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999), 'role' => 'staff',
            'staff_type' => 'nurse', 'restaurant_id' => $manager->restaurant_id, 'password' => bcrypt('password'),
        ]);
        NursingAssignment::create([
            'restaurant_id' => $manager->restaurant_id, 'hospital_admission_id' => $admission->id,
            'nurse_id' => $nurse->id, 'assigned_at' => now()->subHour(), 'status' => 'active', 'shift' => 'morning',
        ]);

        $scheduled = $order->scheduled_start_at->format('Y-m-d H:i:s');
        $this->actingAs($nurse)->post(route('manager.hospital-medication.administrations.store', $order), [
            'dose' => '500 mg', 'route' => 'oral', 'scheduled_at' => $scheduled,
            'status' => 'administered', 'administered_at' => now()->format('Y-m-d H:i:s'),
            'idempotency_key' => 'dose-1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $record = HospitalMedicationAdministration::firstOrFail();
        $this->assertSame('administered', $record->status);
        $this->assertSame($nurse->id, $record->administered_by);
        $this->assertDatabaseHas('hospital_medication_administrations', ['recorded_by' => $nurse->id, 'idempotency_key' => 'dose-1']);
    }

    public function test_administration_cannot_exceed_dispensed_quantity(): void
    {
        [, $manager, , $admission, $medicine] = $this->context();
        $this->actingAs($manager)->post(route('manager.hospital-medication.orders.store'), $this->orderPayload($admission, $medicine));
        $order = HospitalMedicationOrder::firstOrFail();
        $batch = MedicineBatch::create([
            'restaurant_id' => $manager->restaurant_id, 'medicine_id' => $medicine->id,
            'batch_number' => 'ADMIN', 'expiry_date' => now()->addDay(), 'quantity' => 2, 'selling_price' => 10,
        ]);
        $this->actingAs($manager)->post(route('manager.hospital-medication.fulfill', $order), ['quantity' => 2]);

        $payload = [
            'dose' => '500 mg', 'route' => 'oral',
            'scheduled_at' => $order->scheduled_start_at->format('Y-m-d H:i:s'),
            'status' => 'administered', 'administered_at' => now()->format('Y-m-d H:i:s'),
            'administered_quantity' => 3,
        ];
        $this->actingAs($manager)->post(route('manager.hospital-medication.administrations.store', $order), $payload)
            ->assertStatus(422);
        $this->assertDatabaseCount('hospital_medication_administrations', 0);
    }

    public function test_authorized_doctor_can_order_but_unassigned_staff_cannot_record(): void
    {
        [, $manager, , $admission, $medicine] = $this->context();
        $doctorUser = User::create([
            'name' => 'Doctor', 'email' => uniqid('doctor-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999), 'role' => 'staff',
            'staff_type' => 'doctor', 'restaurant_id' => $manager->restaurant_id, 'password' => bcrypt('password'),
        ]);
        $doctor = Doctor::create(['restaurant_id' => $manager->restaurant_id, 'user_id' => $doctorUser->id, 'name' => 'Doctor', 'specialty' => 'Internal medicine']);
        $admission->update(['doctor_id' => $doctor->id]);

        $this->actingAs($doctorUser)->post(route('manager.hospital-medication.orders.store'), $this->orderPayload($admission, $medicine))
            ->assertRedirect()->assertSessionHasNoErrors();
        $order = HospitalMedicationOrder::firstOrFail();

        $nurse = User::create([
            'name' => 'Unassigned Nurse', 'email' => uniqid('nurse-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999), 'role' => 'staff',
            'staff_type' => 'nurse', 'restaurant_id' => $manager->restaurant_id, 'password' => bcrypt('password'),
        ]);
        $this->actingAs($nurse)->post(route('manager.hospital-medication.administrations.store', $order), [
            'dose' => '500 mg', 'route' => 'oral', 'scheduled_at' => $order->scheduled_start_at->format('Y-m-d H:i:s'),
            'status' => 'administered', 'administered_at' => now()->format('Y-m-d H:i:s'),
        ])->assertForbidden();
    }

    public function test_order_and_administration_enforce_tenant_and_admission_windows(): void
    {
        [$restaurant, $manager, , $admission, $medicine] = $this->context();
        $other = Restaurant::create(['name' => 'Other', 'slug' => 'other-' . uniqid(), 'status' => 'active', 'enabled_modules' => ['hospital-medication']]);
        $otherPatient = Patient::create(['restaurant_id' => $other->id, 'patient_number' => 'PAT-' . uniqid(), 'name' => 'Other']);
        $otherAdmission = HospitalAdmission::create(['restaurant_id' => $other->id, 'admission_number' => 'ADM-' . uniqid(), 'patient_id' => $otherPatient->id, 'status' => 'admitted', 'admitted_at' => now()->subDay()]);

        $this->actingAs($manager)->post(route('manager.hospital-medication.orders.store'), $this->orderPayload($otherAdmission, $medicine))
            ->assertNotFound();

        $payload = $this->orderPayload($admission, $medicine);
        $payload['scheduled_start_at'] = $admission->admitted_at->copy()->subMinute()->format('Y-m-d H:i:s');
        $this->actingAs($manager)->post(route('manager.hospital-medication.orders.store'), $payload)
            ->assertStatus(422);
        $this->assertSame($restaurant->id, $manager->restaurant_id);
        $this->assertDatabaseCount('hospital_medication_orders', 0);
    }

    public function test_duplicate_and_idempotent_administrations_are_rejected_without_extra_rows(): void
    {
        [, $manager, , $admission, $medicine] = $this->context();
        $this->actingAs($manager)->post(route('manager.hospital-medication.orders.store'), $this->orderPayload($admission, $medicine));
        $order = HospitalMedicationOrder::firstOrFail();
        $payload = [
            'dose' => '500 mg', 'route' => 'oral', 'scheduled_at' => $order->scheduled_start_at->format('Y-m-d H:i:s'),
            'status' => 'omitted', 'reason' => 'Patient unavailable', 'idempotency_key' => 'duplicate-dose',
        ];

        $this->actingAs($manager)->post(route('manager.hospital-medication.administrations.store', $order), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('manager.hospital-medication.administrations.store', $order), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('hospital_medication_administrations', 1);
    }

    public function test_manager_can_fulfill_from_earliest_valid_batch_and_create_pharmacy_charge(): void
    {
        [, $manager, , $admission, $medicine] = $this->context();
        MedicineBatch::create([
            'restaurant_id' => $manager->restaurant_id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'EXPIRED',
            'expiry_date' => now()->subDay(),
            'quantity' => 50,
            'selling_price' => 9.5,
        ]);
        $validBatch = MedicineBatch::create([
            'restaurant_id' => $manager->restaurant_id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'VALID',
            'expiry_date' => now()->addMonth(),
            'quantity' => 5,
            'selling_price' => 10,
        ]);

        $this->actingAs($manager)->post(route('manager.hospital-medication.orders.store'), $this->orderPayload($admission, $medicine));
        $order = HospitalMedicationOrder::firstOrFail();

        $this->actingAs($manager)->post(route('manager.hospital-medication.fulfill', $order), [
            'quantity' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $fulfillment = HospitalMedicationFulfillment::firstOrFail();
        $this->assertSame('dispensed', $fulfillment->status);
        $this->assertSame($validBatch->id, $fulfillment->medicine_batch_id);
        $this->assertSame(2.0, (float) $validBatch->fresh()->quantity);
        $this->assertDatabaseHas('hospital_billings', [
            'id' => $fulfillment->hospital_billing_id,
            'charge_type' => 'pharmacy',
            'status' => 'draft',
        ]);
    }

    public function test_fulfillment_rejects_out_of_stock_and_non_manager_staff(): void
    {
        [, $manager, , $admission, $medicine] = $this->context();
        $this->actingAs($manager)->post(route('manager.hospital-medication.orders.store'), $this->orderPayload($admission, $medicine));
        $order = HospitalMedicationOrder::firstOrFail();
        $doctor = User::create([
            'name' => 'Doctor', 'email' => uniqid('fulfill-doctor-').'@example.com',
            'phone' => '03000'.random_int(100000, 999999), 'role' => 'staff',
            'staff_type' => 'doctor', 'restaurant_id' => $manager->restaurant_id, 'password' => bcrypt('password'),
        ]);

        $this->actingAs($doctor)->post(route('manager.hospital-medication.fulfill', $order), ['quantity' => 1])
            ->assertForbidden();
        $this->actingAs($manager)->post(route('manager.hospital-medication.fulfill', $order), ['quantity' => 1])
            ->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('hospital_medication_fulfillments', 0);
    }

    public function test_fulfillment_uses_multiple_batches_and_records_partial_stock(): void
    {
        [, $manager, , $admission, $medicine] = $this->context();
        $firstBatch = MedicineBatch::create([
            'restaurant_id' => $manager->restaurant_id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'FIRST',
            'expiry_date' => now()->addDays(5),
            'quantity' => 2,
            'selling_price' => 10,
        ]);
        $secondBatch = MedicineBatch::create([
            'restaurant_id' => $manager->restaurant_id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'SECOND',
            'expiry_date' => now()->addDays(15),
            'quantity' => 3,
            'selling_price' => 10,
        ]);

        $this->actingAs($manager)->post(route('manager.hospital-medication.orders.store'), $this->orderPayload($admission, $medicine));
        $order = HospitalMedicationOrder::firstOrFail();

        $this->actingAs($manager)->post(route('manager.hospital-medication.fulfill', $order), [
            'quantity' => 8,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $fulfillment = HospitalMedicationFulfillment::with('batches')->firstOrFail();
        $this->assertSame('partially_dispensed', $fulfillment->status);
        $this->assertSame(5.0, (float) $fulfillment->quantity_dispensed);
        $this->assertSame(0.0, (float) $firstBatch->fresh()->quantity);
        $this->assertSame(0.0, (float) $secondBatch->fresh()->quantity);
        $this->assertSame(2, $fulfillment->batches->count());
        $this->assertSame(5.0, (float) $fulfillment->batches->sum('quantity_dispensed'));

        $thirdBatch = MedicineBatch::create([
            'restaurant_id' => $manager->restaurant_id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'THIRD',
            'expiry_date' => now()->addDays(30),
            'quantity' => 3,
            'selling_price' => 10,
        ]);

        $this->actingAs($manager)->post(route('manager.hospital-medication.fulfill', $order), [
            'quantity' => 8,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $fulfillment->refresh();
        $this->assertSame('dispensed', $fulfillment->status);
        $this->assertSame(8.0, (float) $fulfillment->quantity_dispensed);
        $this->assertSame(0.0, (float) $thirdBatch->fresh()->quantity);
        $this->assertSame(3, $fulfillment->batches()->count());
        $this->assertDatabaseCount('hospital_billings', 2);
    }

    public function test_administered_timestamp_cannot_be_before_schedule_or_in_the_future(): void
    {
        [, $manager, , $admission, $medicine] = $this->context();
        $this->actingAs($manager)->post(route('manager.hospital-medication.orders.store'), $this->orderPayload($admission, $medicine));
        $order = HospitalMedicationOrder::firstOrFail();
        $this->actingAs($manager)->post(route('manager.hospital-medication.administrations.store', $order), [
            'dose' => '500 mg', 'route' => 'oral', 'scheduled_at' => $order->scheduled_start_at->format('Y-m-d H:i:s'),
            'status' => 'administered', 'administered_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ])->assertStatus(422);
        $this->actingAs($manager)->post(route('manager.hospital-medication.administrations.store', $order), [
            'dose' => '500 mg', 'route' => 'oral', 'scheduled_at' => $order->scheduled_start_at->format('Y-m-d H:i:s'),
            'status' => 'administered', 'administered_at' => now()->addMinute()->format('Y-m-d H:i:s'),
        ])->assertStatus(422);
    }
}
