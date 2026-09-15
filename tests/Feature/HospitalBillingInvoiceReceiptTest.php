<?php

namespace Tests\Feature;

use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalBillingInvoiceReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function setupHospital(string $suffix = ''): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Professional Hospital ' . $suffix,
            'slug' => 'professional-billing-' . uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions', 'hospital-billing'],
        ]);
        $manager = User::create([
            'name' => 'Billing Manager',
            'email' => uniqid('billing-manager-') . '@example.com',
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

        return [$restaurant, $manager, $admission];
    }

    private function charge(HospitalAdmission $admission, string $status = 'posted'): HospitalBilling
    {
        return HospitalBilling::create([
            'restaurant_id' => $admission->restaurant_id,
            'patient_id' => $admission->patient_id,
            'hospital_admission_id' => $admission->id,
            'charge_type' => 'bed',
            'description' => 'Inpatient room',
            'quantity' => 1,
            'unit_amount' => 250,
            'status' => $status,
            'charged_at' => now()->subHour(),
        ]);
    }

    public function test_invoice_numbers_are_generated_and_unique_within_a_tenant(): void
    {
        [$restaurant, , $admission] = $this->setupHospital();
        $first = $this->charge($admission, 'draft');
        $second = $this->charge($admission, 'draft');

        $this->assertNotSame($first->invoice_number, $second->invoice_number);
        $this->assertStringStartsWith('INV-' . $restaurant->id . '-', $first->invoice_number);
        $this->assertDatabaseCount('hospital_billings', 2);
    }

    public function test_manager_can_pay_charge_and_view_printable_receipt(): void
    {
        [, $manager, $admission] = $this->setupHospital();
        $billing = $this->charge($admission);

        $this->actingAs($manager)->patch(route('manager.hospital-billing.pay', $billing), [
            'payment_method' => 'card',
            'payment_reference' => 'CARD-123',
            'payment_notes' => 'Paid at inpatient desk',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $billing->refresh();
        $this->assertSame('paid', $billing->status);
        $this->assertNotNull($billing->receipt_number);
        $this->assertSame('card', $billing->payment_method);

        $this->actingAs($manager)
            ->get(route('manager.hospital-billing.receipt', $billing))
            ->assertOk()
            ->assertSee($billing->invoice_number)
            ->assertSee($billing->receipt_number)
            ->assertSee('CARD-123')
            ->assertSee('Print');
    }

    public function test_payment_receipt_is_manager_only_and_paid_only(): void
    {
        [, $manager, $admission] = $this->setupHospital();
        $draft = $this->charge($admission, 'draft');

        $this->actingAs($manager)
            ->get(route('manager.hospital-billing.receipt', $draft))
            ->assertStatus(422);

        $doctor = User::create([
            'name' => 'Doctor',
            'email' => uniqid('doctor-') . '@example.com',
            'phone' => '03000' . random_int(100000, 999999),
            'role' => 'staff',
            'staff_type' => 'doctor',
            'restaurant_id' => $manager->restaurant_id,
            'password' => bcrypt('password'),
        ]);
        $paid = $this->charge($admission);
        $paid->update(['status' => 'paid', 'payment_reference' => 'LEGACY-1']);

        $this->actingAs($doctor)
            ->get(route('manager.hospital-billing.receipt', $paid))
            ->assertForbidden();
    }

    public function test_receipt_route_cannot_cross_tenant_boundary(): void
    {
        [, $manager, $admission] = $this->setupHospital('one');
        $billing = $this->charge($admission, 'paid');
        $billing->update(['receipt_number' => 'RCT-ONE', 'payment_reference' => 'ONE']);
        [, $otherManager] = $this->setupHospital('two');

        $this->actingAs($otherManager)
            ->get(route('manager.hospital-billing.receipt', $billing))
            ->assertNotFound();
    }
}
