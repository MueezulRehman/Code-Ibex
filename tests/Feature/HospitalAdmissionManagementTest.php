<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
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

}
