<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\HospitalBillingPayment;
use App\Models\HospitalCarePlan;
use App\Models\HospitalClinicalNote;
use App\Models\HospitalMedicationAdministration;
use App\Models\HospitalMedicationOrder;
use App\Models\HospitalPatientInsurance;
use App\Models\HospitalStaffRoster;
use App\Models\InsuranceProvider;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Medicine;
use App\Models\NursingAssignment;
use App\Models\OperatingRoom;
use App\Models\Patient;
use App\Models\RadiologyOrder;
use App\Models\RadiologyResult;
use App\Models\Report;
use App\Models\Surgery;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardLocation;
use App\Models\Restaurant;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Small, deterministic Professional Hospital dataset for demos and browser
 * verification. Every lookup is scoped by the selected restaurant and can be
 * run repeatedly without creating duplicate rows.
 */
class ProfessionalHospitalDemoSeeder extends Seeder
{
    protected ?Restaurant $restaurant = null;

    public function setRestaurant(Restaurant $restaurant): self
    {
        $this->restaurant = $restaurant;

        return $this;
    }

    /**
     * Seed the selected Professional Hospital. The command is the preferred
     * entry point because it enforces tenant selection and dry-run behavior.
     */
    public function run(): void
    {
        if (! $this->restaurant) {
            throw new \InvalidArgumentException('A Professional Hospital tenant must be selected explicitly.');
        }

        $restaurant = $this->restaurant;
        if (strcasecmp((string) $restaurant->businessType?->name, 'Professional Hospital') !== 0) {
            throw new \InvalidArgumentException('Demo data may only be seeded for a Professional Hospital tenant.');
        }
        if (! $restaurant->hasTenantDatabase()) {
            throw new \RuntimeException("Restaurant #{$restaurant->id} has no dedicated tenant database.");
        }

        Tenancy::runFor($restaurant, function () use ($restaurant): void {
            $connection = config('tenancy.connection', 'tenant');

            // Keep the base tenant schema's restaurant and users FK targets in
            // sync before creating hospital records.
            app(TenantDatabaseSeeder::class)->setRestaurant($restaurant)->run();

            $staff = $this->seedStaffMirrors($restaurant, $connection);
            $departments = $this->seedDepartments($restaurant);
            $doctors = $this->seedDoctors($restaurant, $departments, $staff);
            [$wards, $locations, $beds] = $this->seedWardsAndBeds($restaurant);
            $patients = $this->seedPatients($restaurant);
            $provider = $this->seedInsuranceProvider($restaurant);
            $policies = $this->seedInsurancePolicies($restaurant, $patients, $provider);
            $admissions = $this->seedAdmissions($restaurant, $patients, $departments, $beds, $policies, $doctors);
            $this->seedNursing($restaurant, $admissions, $staff, $departments, $wards);
            $this->seedVitals($restaurant, $admissions, $patients, $staff);
            [$labOrders, $radiologyOrders] = $this->seedDiagnostics($restaurant, $admissions, $patients, $staff);
            $this->seedSurgery($restaurant, $admissions, $patients, $staff);
            $this->seedMedication($restaurant, $admissions, $patients, $staff);
            $this->seedClinicalRecords($restaurant, $admissions, $patients, $staff);
            $this->seedBilling($restaurant, $admissions, $patients, $policies, $staff);
            $this->seedReports($restaurant, $staff, $labOrders, $radiologyOrders, $admissions);
        });
    }

    /**
     * Create central users, then mirror the minimal FK rows into the tenant
     * database. Tenant users intentionally use the schema's supported
     * `admin` role; the central records retain the staff-facing role.
     */
    protected function seedStaffMirrors(Restaurant $restaurant, string $connection): array
    {
        $definitions = [
            ['key' => 'doctor', 'name' => 'Dr. Ayesha Khan', 'email' => 'demo.doctor@professional-hospital.test', 'phone' => '03000018001', 'role' => 'manager'],
            ['key' => 'nurse', 'name' => 'Nurse Sara Ahmed', 'email' => 'demo.nurse@professional-hospital.test', 'phone' => '03000018002', 'role' => 'manager'],
            ['key' => 'billing', 'name' => 'Bilal Hussain', 'email' => 'demo.billing@professional-hospital.test', 'phone' => '03000018003', 'role' => 'manager'],
        ];

        $staff = [];
        foreach ($definitions as $definition) {
            $staff[$definition['key']] = User::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'phone' => $definition['phone'],
                    'role' => $definition['role'],
                    'restaurant_id' => $restaurant->id,
                    'is_active' => true,
                    'password' => bcrypt('password'),
                ]
            );
        }

        $now = now();
        foreach ($staff as $user) {
            DB::connection($connection)->table('users')->updateOrInsert(
                ['id' => $user->id],
                [
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'role' => 'admin',
                    'password' => $user->password,
                    'is_active' => true,
                    'joined_at' => $user->joined_at?->format('Y-m-d'),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        return $staff;
    }

    protected function seedDepartments(Restaurant $restaurant): array
    {
        $rows = [
            ['code' => 'ER', 'name' => 'Emergency & Trauma', 'description' => 'Emergency assessment and stabilization.'],
            ['code' => 'MED', 'name' => 'Internal Medicine', 'description' => 'Adult medicine and inpatient rounds.'],
            ['code' => 'SURG', 'name' => 'General Surgery', 'description' => 'Elective and urgent surgical care.'],
        ];

        $departments = [];
        foreach ($rows as $row) {
            $departments[$row['code']] = Department::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => $row['code']],
                array_merge($row, ['restaurant_id' => $restaurant->id, 'is_active' => true])
            );
        }

        return $departments;
    }

    protected function seedDoctors(Restaurant $restaurant, array $departments, array $staff): array
    {
        return [
            'doctor' => Doctor::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'user_id' => $staff['doctor']->id],
                [
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $staff['doctor']->id,
                    'department_id' => $departments['MED']->id,
                    'name' => $staff['doctor']->name,
                    'specialty' => 'Internal Medicine',
                    'phone' => $staff['doctor']->phone,
                    'email' => $staff['doctor']->email,
                    'status' => 'approved',
                ]
            ),
        ];
    }

    protected function seedWardsAndBeds(Restaurant $restaurant): array
    {
        $wardRows = [
            ['code' => 'MW1', 'name' => 'Medical Ward 1', 'description' => 'General medical inpatient ward.'],
            ['code' => 'ICU', 'name' => 'Intensive Care Unit', 'description' => 'Monitored critical care beds.'],
        ];
        $wards = [];
        $locations = [];
        $beds = [];

        foreach ($wardRows as $row) {
            $ward = Ward::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => $row['code']],
                array_merge($row, ['restaurant_id' => $restaurant->id, 'is_active' => true])
            );
            $wards[$row['code']] = $ward;

            $locationCode = $row['code'] . '-A';
            $location = WardLocation::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ward_id' => $ward->id, 'code' => $locationCode],
                [
                    'restaurant_id' => $restaurant->id,
                    'ward_id' => $ward->id,
                    'type' => 'room',
                    'name' => $row['name'] . ' Room A',
                    'code' => $locationCode,
                    'description' => 'Demo room location.',
                    'is_active' => true,
                ]
            );
            $locations[$row['code']] = $location;

            foreach (['01', '02'] as $bedNumber) {
                $code = $row['code'] . '-' . $bedNumber;
                $beds[$code] = Bed::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'code' => $code],
                    [
                        'restaurant_id' => $restaurant->id,
                        'ward_id' => $ward->id,
                        'location_id' => $location->id,
                        'name' => 'Bed ' . $bedNumber,
                        'code' => $code,
                        'status' => $code === 'MW1-02' ? 'occupied' : 'available',
                    ]
                );
            }
        }

        return [$wards, $locations, $beds];
    }

    protected function seedPatients(Restaurant $restaurant): array
    {
        $rows = [
            ['number' => 'PH-DEMO-0001', 'name' => 'Muhammad Usman', 'phone' => '03000018101', 'gender' => 'male', 'dob' => '1985-04-18'],
            ['number' => 'PH-DEMO-0002', 'name' => 'Fatima Noor', 'phone' => '03000018102', 'gender' => 'female', 'dob' => '1992-11-03'],
            ['number' => 'PH-DEMO-0003', 'name' => 'Hassan Raza', 'phone' => '03000018103', 'gender' => 'male', 'dob' => '1978-08-27'],
        ];
        $patients = [];

        foreach ($rows as $row) {
            $patients[$row['number']] = Patient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'patient_number' => $row['number']],
                [
                    'restaurant_id' => $restaurant->id,
                    'patient_number' => $row['number'],
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'email' => strtolower(str_replace(' ', '.', $row['name'])) . '@demo.patient.test',
                    'date_of_birth' => $row['dob'],
                    'gender' => $row['gender'],
                    'notification_consent' => true,
                    'address' => 'Professional Hospital Demo District',
                    'emergency_contact_name' => 'Demo family contact',
                    'emergency_contact_phone' => '03000018999',
                ]
            );
        }

        return $patients;
    }

    protected function seedInsuranceProvider(Restaurant $restaurant): InsuranceProvider
    {
        return InsuranceProvider::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Demo Health Assurance'],
            [
                'restaurant_id' => $restaurant->id,
                'name' => 'Demo Health Assurance',
                'phone' => '021-5550180',
                'email' => 'claims@demo-health.test',
                'is_active' => true,
            ]
        );
    }

    protected function seedInsurancePolicies(Restaurant $restaurant, array $patients, InsuranceProvider $provider): array
    {
        $policies = [];
        foreach (array_values($patients) as $index => $patient) {
            $policyNumber = 'DHA-PH-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $policies[$patient->patient_number] = HospitalPatientInsurance::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'policy_number' => $policyNumber],
                [
                    'restaurant_id' => $restaurant->id,
                    'patient_id' => $patient->id,
                    'provider_id' => $provider->id,
                    'payer' => $provider->name,
                    'policy_number' => $policyNumber,
                    'member_number' => 'MEM-' . ($index + 1) . '8018',
                    'coverage_starts' => now()->subYear()->toDateString(),
                    'coverage_ends' => now()->addYear()->toDateString(),
                    'status' => 'active',
                ]
            );
        }

        return $policies;
    }

    protected function seedAdmissions(Restaurant $restaurant, array $patients, array $departments, array $beds, array $policies, array $doctors): array
    {
        $admissions = [];
        $rows = [
            ['number' => 'ADM-DEMO-0001', 'patient' => 'PH-DEMO-0001', 'department' => 'MED', 'bed' => 'MW1-02', 'status' => 'admitted'],
            ['number' => 'ADM-DEMO-0002', 'patient' => 'PH-DEMO-0002', 'department' => 'SURG', 'bed' => 'MW1-01', 'status' => 'admitted'],
            ['number' => 'ADM-DEMO-0003', 'patient' => 'PH-DEMO-0003', 'department' => 'MED', 'bed' => 'ICU-01', 'status' => 'discharged'],
        ];

        foreach ($rows as $row) {
            $admissions[$row['number']] = HospitalAdmission::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'admission_number' => $row['number']],
                [
                    'restaurant_id' => $restaurant->id,
                    'admission_number' => $row['number'],
                    'patient_id' => $patients[$row['patient']]->id,
                    'hospital_patient_insurance_id' => $policies[$row['patient']]->id,
                    'doctor_id' => $doctors['doctor']->id,
                    'department_id' => $departments[$row['department']]->id,
                    'bed_id' => $beds[$row['bed']]->id,
                    'status' => $row['status'],
                    'admitted_at' => now()->subDays(2),
                    'discharged_at' => $row['status'] === 'discharged' ? now()->subDay() : null,
                    'notes' => 'Seeded demo admission for browser verification.',
                ]
            );
        }

        return $admissions;
    }

    protected function seedNursing(Restaurant $restaurant, array $admissions, array $staff, array $departments, array $wards): void
    {
        foreach (array_values($admissions) as $index => $admission) {
            NursingAssignment::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'hospital_admission_id' => $admission->id, 'nurse_id' => $staff['nurse']->id, 'shift' => 'morning'],
                [
                    'restaurant_id' => $restaurant->id,
                    'hospital_admission_id' => $admission->id,
                    'nurse_id' => $staff['nurse']->id,
                    'assigned_at' => now()->subDays(2),
                    'ended_at' => $admission->status === 'discharged' ? now()->subDay() : null,
                    'status' => $admission->status === 'discharged' ? 'completed' : 'active',
                    'shift' => 'morning',
                    'notes' => 'Demo nurse assignment.',
                ]
            );

            HospitalStaffRoster::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'staff_user_id' => $staff['nurse']->id, 'shift_date' => now()->addDays($index)->toDateString(), 'shift_type' => 'morning'],
                [
                    'restaurant_id' => $restaurant->id,
                    'staff_user_id' => $staff['nurse']->id,
                    'shift_date' => now()->addDays($index)->toDateString(),
                    'starts_at' => now()->addDays($index)->setTime(7, 0),
                    'ends_at' => now()->addDays($index)->setTime(15, 0),
                    'shift_type' => 'morning',
                    'status' => 'scheduled',
                    'department_id' => $departments['MED']->id,
                    'ward_id' => $wards['MW1']->id,
                    'notes' => 'Demo nursing roster.',
                ]
            );
        }
    }

    protected function seedVitals(Restaurant $restaurant, array $admissions, array $patients, array $staff): void
    {
        $base = now()->startOfDay();
        foreach (array_values($admissions) as $index => $admission) {
            $patient = $patients[$admission->patient_id === $patients['PH-DEMO-0001']->id ? 'PH-DEMO-0001' : ($index === 1 ? 'PH-DEMO-0002' : 'PH-DEMO-0003')];
            $recordedAt = $base->copy()->subHours(4 + $index);
            VitalSign::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'hospital_admission_id' => $admission->id, 'recorded_at' => $recordedAt],
                [
                    'restaurant_id' => $restaurant->id,
                    'patient_id' => $patient->id,
                    'hospital_admission_id' => $admission->id,
                    'recorded_by' => $staff['nurse']->id,
                    'recorded_at' => $recordedAt,
                    'temperature' => 37.1 + $index / 10,
                    'blood_pressure' => '120/80',
                    'pulse' => 76 + $index,
                    'respiratory_rate' => 18,
                    'oxygen_saturation' => 98,
                    'weight' => 72.5,
                    'pain_score' => $index,
                    'notes' => 'Stable demo observation.',
                ]
            );
        }
    }

    protected function seedDiagnostics(Restaurant $restaurant, array $admissions, array $patients, array $staff): array
    {
        $labOrders = [];
        $radiologyOrders = [];
        $admission = array_values($admissions)[0];
        $patient = array_values($patients)[0];
        $orderedAt = now()->subDay();

        $labOrders['cbc'] = LabOrder::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'order_number' => 'LAB-DEMO-0001'],
            [
                'restaurant_id' => $restaurant->id, 'patient_id' => $patient->id, 'hospital_admission_id' => $admission->id,
                'ordered_by' => $staff['doctor']->id, 'order_number' => 'LAB-DEMO-0001', 'test_name' => 'Complete Blood Count',
                'priority' => 'routine', 'status' => 'resulted', 'ordered_at' => $orderedAt, 'clinical_notes' => 'Routine admission screen.',
            ]
        );
        LabResult::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'lab_order_id' => $labOrders['cbc']->id],
            [
                'restaurant_id' => $restaurant->id, 'lab_order_id' => $labOrders['cbc']->id, 'patient_id' => $patient->id,
                'hospital_admission_id' => $admission->id, 'entered_by' => $staff['nurse']->id, 'verified_by' => $staff['doctor']->id,
                'result_value' => 'Hb 13.8 g/dL; WBC 7.2 x10^9/L', 'status' => 'verified',
                'resulted_at' => now()->subHours(8), 'verified_at' => now()->subHours(6), 'notes' => 'Within expected range.',
            ]
        );

        $radiologyOrders['xray'] = RadiologyOrder::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'order_number' => 'RAD-DEMO-0001'],
            [
                'restaurant_id' => $restaurant->id, 'patient_id' => $patient->id, 'hospital_admission_id' => $admission->id,
                'ordered_by' => $staff['doctor']->id, 'order_number' => 'RAD-DEMO-0001', 'study_name' => 'Chest X-Ray',
                'priority' => 'routine', 'status' => 'resulted', 'ordered_at' => $orderedAt, 'clinical_notes' => 'Pre-operative baseline.',
            ]
        );
        RadiologyResult::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'radiology_order_id' => $radiologyOrders['xray']->id],
            [
                'restaurant_id' => $restaurant->id, 'radiology_order_id' => $radiologyOrders['xray']->id, 'patient_id' => $patient->id,
                'hospital_admission_id' => $admission->id, 'entered_by' => $staff['nurse']->id, 'verified_by' => $staff['doctor']->id,
                'result_value' => 'No acute cardiopulmonary abnormality.', 'status' => 'verified',
                'resulted_at' => now()->subHours(7), 'verified_at' => now()->subHours(5), 'notes' => 'Demo radiology result.',
            ]
        );

        return [$labOrders, $radiologyOrders];
    }

    protected function seedSurgery(Restaurant $restaurant, array $admissions, array $patients, array $staff): void
    {
        $room = OperatingRoom::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'OT-1'],
            ['restaurant_id' => $restaurant->id, 'name' => 'Operating Room 1', 'code' => 'OT-1', 'status' => 'available', 'notes' => 'Demo theatre.']
        );
        $admission = array_values($admissions)[1];
        $patient = array_values($patients)[1];
        Surgery::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'surgery_number' => 'SURG-DEMO-0001'],
            [
                'restaurant_id' => $restaurant->id, 'patient_id' => $patient->id, 'hospital_admission_id' => $admission->id,
                'operating_room_id' => $room->id, 'surgeon_id' => $staff['doctor']->id, 'scheduled_by' => $staff['billing']->id,
                'surgery_number' => 'SURG-DEMO-0001', 'procedure_name' => 'Laparoscopic cholecystectomy',
                'status' => 'scheduled', 'consent_status' => 'signed', 'preop_status' => 'cleared',
                'preop_checklist' => ['identity_verified' => true, 'consent' => true, 'fasting_confirmed' => true],
                'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(12, 0),
                'notes' => 'Seeded operating theatre case.',
            ]
        );
    }

    protected function seedMedication(Restaurant $restaurant, array $admissions, array $patients, array $staff): void
    {
        $medicine = Medicine::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'sku' => 'PH-DEMO-AMOX'],
            [
                'restaurant_id' => $restaurant->id, 'name' => 'Amoxicillin 500mg', 'generic_name' => 'Amoxicillin',
                'dosage_form' => 'Capsule', 'strength' => '500 mg', 'sku' => 'PH-DEMO-AMOX',
                'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Demo medication.',
            ]
        );
        $admission = array_values($admissions)[0];
        $patient = array_values($patients)[0];
        $order = HospitalMedicationOrder::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'order_number' => 'MED-DEMO-0001'],
            [
                'restaurant_id' => $restaurant->id, 'patient_id' => $patient->id, 'hospital_admission_id' => $admission->id,
                'medicine_id' => $medicine->id, 'ordered_by' => $staff['doctor']->id, 'order_number' => 'MED-DEMO-0001',
                'dose' => '500 mg', 'route' => 'oral', 'frequency' => 'every 8 hours', 'scheduled_start_at' => now()->subDay(),
                'scheduled_end_at' => now()->addDays(4), 'status' => 'active', 'ordered_at' => now()->subDay(), 'notes' => 'Demo antibiotic order.',
            ]
        );
        HospitalMedicationAdministration::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'idempotency_key' => 'PH-DEMO-MED-0001'],
            [
                'restaurant_id' => $restaurant->id, 'patient_id' => $patient->id, 'hospital_admission_id' => $admission->id,
                'hospital_medication_order_id' => $order->id, 'recorded_by' => $staff['nurse']->id, 'administered_by' => $staff['nurse']->id,
                'dose' => '500 mg', 'route' => 'oral', 'scheduled_at' => now()->subHours(3), 'administered_at' => now()->subHours(3),
                'status' => 'administered', 'idempotency_key' => 'PH-DEMO-MED-0001', 'recorded_at' => now()->subHours(3),
                'notes' => 'Demo administration record.',
            ]
        );
    }

    protected function seedClinicalRecords(Restaurant $restaurant, array $admissions, array $patients, array $staff): void
    {
        $base = now()->startOfDay();
        $admission = array_values($admissions)[0];
        $patient = array_values($patients)[0];
        $recordedAt = $base->copy()->subHours(2);
        HospitalClinicalNote::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'hospital_admission_id' => $admission->id, 'note_type' => 'progress', 'recorded_at' => $recordedAt],
            [
                'restaurant_id' => $restaurant->id, 'hospital_admission_id' => $admission->id, 'author_id' => $staff['doctor']->id,
                'note_type' => 'progress', 'content' => 'Patient improving; continue current plan and review labs.',
                'recorded_at' => $recordedAt, 'status' => 'active',
            ]
        );
        HospitalCarePlan::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'hospital_admission_id' => $admission->id, 'problem_diagnosis' => 'Acute pain'],
            [
                'restaurant_id' => $restaurant->id, 'hospital_admission_id' => $admission->id, 'patient_id' => $patient->id,
                'problem_diagnosis' => 'Acute pain', 'goal' => 'Pain below 3/10 within 24 hours.',
                'interventions' => 'Assess pain each shift and administer prescribed analgesia.',
                'responsible_role' => 'nurse', 'responsible_user_id' => $staff['nurse']->id, 'status' => 'active',
                'opened_at' => now()->subDay(), 'review_due_at' => now()->addDay(),
                'nursing_progress' => 'Patient reports improved comfort.', 'nursing_status' => 'progressing',
                'nursing_progress_at' => now()->subHours(2), 'notes' => 'Demo care plan.',
            ]
        );
    }

    protected function seedBilling(Restaurant $restaurant, array $admissions, array $patients, array $policies, array $staff): void
    {
        $admission = array_values($admissions)[0];
        $patient = array_values($patients)[0];
        $policy = $policies[$patient->patient_number];
        $billing = HospitalBilling::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'invoice_number' => 'INV-PH-DEMO-0001'],
            [
                'restaurant_id' => $restaurant->id, 'patient_id' => $patient->id, 'hospital_admission_id' => $admission->id,
                'hospital_patient_insurance_id' => $policy->id, 'charge_type' => 'room',
                'description' => 'Medical ward accommodation and nursing package', 'quantity' => 1, 'unit_amount' => 500,
                'total_amount' => 500, 'status' => 'posted', 'charged_at' => now()->subDay(),
                'posted_by' => $staff['billing']->id, 'posted_at' => now()->subDay(), 'invoice_number' => 'INV-PH-DEMO-0001',
                'notes' => 'Demo posted hospital charge.',
            ]
        );
        HospitalBillingPayment::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'hospital_billing_id' => $billing->id, 'reference' => 'PAY-PH-DEMO-0001'],
            [
                'restaurant_id' => $restaurant->id, 'hospital_billing_id' => $billing->id, 'hospital_admission_id' => $admission->id,
                'type' => 'payment', 'amount' => 300, 'method' => 'insurance', 'reference' => 'PAY-PH-DEMO-0001',
                'recorded_by' => $staff['billing']->id, 'recorded_at' => now()->subHours(5), 'status' => 'posted',
                'notes' => 'Demo insurance advance payment.',
            ]
        );
    }

    protected function seedReports(Restaurant $restaurant, array $staff, array $labOrders, array $radiologyOrders, array $admissions): void
    {
        Report::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'type' => 'financial', 'name' => 'Professional Hospital Demo Census'],
            [
                'restaurant_id' => $restaurant->id, 'user_id' => $staff['billing']->id, 'type' => 'financial',
                'name' => 'Professional Hospital Demo Census',
                'filters' => ['source' => 'professional-hospital-demo', 'period' => 'current'],
                'data_snapshot' => [
                    'admissions' => count($admissions),
                    'lab_orders' => count($labOrders),
                    'radiology_orders' => count($radiologyOrders),
                    'generated_by' => 'ProfessionalHospitalDemoSeeder',
                ],
                'generated_at' => now(),
            ]
        );
    }
}
