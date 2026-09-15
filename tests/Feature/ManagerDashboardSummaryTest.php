<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\User;
use App\Models\Patient;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\LabOrder;
use App\Models\RadiologyOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_dashboard_shows_period_income_and_expense_summary(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Cafe',
            'slug' => 'test-cafe',
            'status' => 'active',
            'enabled_modules' => ['menu'],
        ]);

        RestaurantSubscription::create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        $manager = User::create([
            'name' => 'Manager One',
            'email' => 'manager-one@example.com',
            'phone' => '03000000001',
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'web')
            ->get('/manager/dashboard')
            ->assertStatus(200)
            ->assertSee('Business Performance')
            ->assertSee('Daily')
            ->assertDontSee('Inpatient operations');
    }

    public function test_professional_hospital_dashboard_shows_tenant_scoped_operations(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Professional Hospital',
            'slug' => 'professional-hospital-dashboard',
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions', 'hospital-billing', 'hospital-laboratory', 'hospital-radiology'],
        ]);
        $other = Restaurant::create(['name' => 'Other Hospital', 'slug' => 'other-hospital-dashboard', 'status' => 'active', 'enabled_modules' => ['hospital-admissions', 'hospital-billing']]);
        $manager = User::create([
            'name' => 'Hospital Manager', 'email' => 'hospital-dashboard@example.com', 'phone' => '03000000002',
            'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password'),
        ]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-DASHBOARD', 'name' => 'Dashboard Patient']);
        $admission = HospitalAdmission::create([
            'restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-DASHBOARD',
            'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => now()->subDay(),
        ]);
        HospitalBilling::create([
            'restaurant_id' => $restaurant->id, 'patient_id' => $patient->id, 'hospital_admission_id' => $admission->id,
            'charge_type' => 'bed', 'description' => 'Unpaid room charge', 'quantity' => 1, 'unit_amount' => 125,
            'total_amount' => 125, 'status' => 'posted', 'charged_at' => now()->subHour(), 'posted_at' => now()->subHour(),
        ]);
        LabOrder::create([
            'restaurant_id' => $restaurant->id, 'patient_id' => $patient->id, 'hospital_admission_id' => $admission->id,
            'ordered_by' => $manager->id, 'order_number' => 'LAB-DASHBOARD', 'test_name' => 'CBC',
            'ordered_at' => now(), 'status' => 'ordered',
        ]);
        RadiologyOrder::create([
            'restaurant_id' => $restaurant->id, 'patient_id' => $patient->id, 'hospital_admission_id' => $admission->id,
            'ordered_by' => $manager->id, 'order_number' => 'RAD-DASHBOARD', 'study_name' => 'Chest X-ray',
            'ordered_at' => now(), 'status' => 'ordered',
        ]);
        $otherPatient = Patient::create(['restaurant_id' => $other->id, 'patient_number' => 'PAT-OTHER-DASHBOARD', 'name' => 'Other Patient']);
        $otherAdmission = HospitalAdmission::create([
            'restaurant_id' => $other->id, 'admission_number' => 'ADM-OTHER-DASHBOARD',
            'patient_id' => $otherPatient->id, 'status' => 'admitted', 'admitted_at' => now()->subDay(),
        ]);
        HospitalBilling::create([
            'restaurant_id' => $other->id, 'patient_id' => $otherPatient->id, 'hospital_admission_id' => $otherAdmission->id,
            'charge_type' => 'bed', 'description' => 'Other tenant charge', 'quantity' => 1, 'unit_amount' => 999,
            'total_amount' => 999, 'status' => 'posted', 'charged_at' => now(), 'posted_at' => now(),
        ]);

        $this->actingAs($manager, 'web')->get('/manager/dashboard')
            ->assertOk()
            ->assertSee('Inpatient operations')
            ->assertSee('Rs. 125.00')
            ->assertSee('Unpaid room charge')
            ->assertSee('Pending lab orders')
            ->assertSee('Pending radiology')
            ->assertDontSee('Business Performance')
            ->assertDontSee('Other tenant charge')
            ->assertDontSee('Rs. 999.00');
    }
}
