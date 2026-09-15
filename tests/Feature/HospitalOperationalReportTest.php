<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Ward;
use App\Services\HospitalOperationalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalOperationalReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_is_tenant_scoped_and_aggregates_census_occupancy_los_and_billing(): void
    {
        [$first, $manager] = $this->hospital('first');
        [$second] = $this->hospital('second');
        $ward = Ward::create(['restaurant_id' => $first->id, 'name' => 'Ward A', 'code' => 'WA']);
        $bed = Bed::create(['restaurant_id' => $first->id, 'ward_id' => $ward->id, 'name' => 'Bed 1', 'code' => 'A-1', 'status' => 'occupied']);
        $patient = Patient::create(['restaurant_id' => $first->id, 'patient_number' => 'P-A', 'name' => 'First Patient']);
        $admission = HospitalAdmission::create([
            'restaurant_id' => $first->id,
            'admission_number' => 'A-A',
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'status' => 'discharged',
            'admitted_at' => now()->subDays(3),
            'discharged_at' => now()->subDay(),
        ]);
        HospitalBilling::create([
            'restaurant_id' => $first->id,
            'patient_id' => $patient->id,
            'hospital_admission_id' => $admission->id,
            'charge_type' => 'bed',
            'description' => 'Bed charge',
            'quantity' => 1,
            'unit_amount' => 125,
            'status' => 'posted',
            'charged_at' => now()->subDay(),
        ]);
        $currentPatient = Patient::create(['restaurant_id' => $first->id, 'patient_number' => 'P-C', 'name' => 'Current Patient']);
        HospitalAdmission::create([
            'restaurant_id' => $first->id,
            'admission_number' => 'A-C',
            'patient_id' => $currentPatient->id,
            'bed_id' => $bed->id,
            'status' => 'admitted',
            'admitted_at' => now()->subHours(4),
        ]);
        $otherPatient = Patient::create(['restaurant_id' => $second->id, 'patient_number' => 'P-B', 'name' => 'Other Patient']);
        HospitalAdmission::create([
            'restaurant_id' => $second->id,
            'admission_number' => 'A-B',
            'patient_id' => $otherPatient->id,
            'status' => 'admitted',
            'admitted_at' => now(),
        ]);

        $report = app(HospitalOperationalReportService::class)->generate($first->id);
        $this->assertSame(1, $report['admission_census']['current']);
        $this->assertSame(1, $report['occupancy']['occupied_beds']);
        $this->assertSame(1, $report['bed_turnover']['discharges']);
        $this->assertSame(2.0, $report['bed_turnover']['average_los_days']);
        $this->assertSame(125.0, $report['billing']['charges']);
        $this->assertSame(125.0, $report['billing']['outstanding']);

        $this->actingAs($manager)
            ->get(route('manager.hospital-reports.index'))
            ->assertOk()
            ->assertSee('Ward A')
            ->assertSee('125.00')
            ->assertDontSee('Other Patient');
    }

    public function test_only_restaurant_managers_can_open_operational_reports(): void
    {
        [$restaurant] = $this->hospital('restricted');
        $staff = User::create([
            'name' => 'Nurse',
            'email' => uniqid('nurse-').'@example.com',
            'phone' => '0300'.random_int(100000, 999999),
            'role' => 'staff',
            'staff_type' => 'nurse',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($staff)
            ->get(route('manager.hospital-reports.index'))
            ->assertForbidden();
    }

    private function hospital(string $suffix): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Operational Hospital '.$suffix,
            'slug' => 'operational-hospital-'.uniqid(),
            'status' => 'active',
            'enabled_modules' => ['hospital-admissions', 'hospital-wards-beds', 'hospital-billing'],
        ]);
        $manager = User::create([
            'name' => 'Hospital Manager '.$suffix,
            'email' => uniqid('hospital-manager-').'@example.com',
            'phone' => '0300'.random_int(100000, 999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);

        return [$restaurant, $manager];
    }
}
