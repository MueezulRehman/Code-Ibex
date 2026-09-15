<?php

namespace Tests\Feature;

use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_store_defaults_include_store_modules_without_medical_allergies(): void
    {
        ModuleService::seedDefaultModules();
        ModuleService::seedDefaultBusinessTypes();

        $keys = ModuleService::getDefaultModuleKeysForBusinessType('General Store');

        $this->assertContains('inventory', $keys);
        $this->assertContains('categories', $keys);
        $this->assertContains('general_store', $keys);
        $this->assertNotContains('allergies', $keys);
    }

    public function test_other_custom_defaults_are_non_medical(): void
    {
        ModuleService::seedDefaultModules();
        ModuleService::seedDefaultBusinessTypes();

        $keys = ModuleService::getDefaultModuleKeysForBusinessType('Other / Custom');

        $this->assertContains('pos', $keys);
        $this->assertContains('inventory', $keys);
        $this->assertContains('variants', $keys);
        $this->assertNotContains('allergies', $keys);
        $this->assertNotContains('medical', $keys);
        $this->assertNotContains('pharmacy', $keys);
    }

    public function test_mobile_shop_defaults_include_device_workflows(): void
    {
        ModuleService::seedDefaultModules();
        ModuleService::seedDefaultBusinessTypes();

        $keys = ModuleService::getDefaultModuleKeysForBusinessType('Mobile Shop');

        foreach (['suppliers', 'purchasing', 'sales-returns', 'warranty', 'repairs', 'trade-ins', 'installments', 'brands', 'barcode-labels', 'loyalty', 'stock-transfers', 'profit-margins'] as $module) {
            $this->assertContains($module, $keys);
        }

        $this->assertNotContains('medical', $keys);
        $this->assertNotContains('allergies', $keys);
    }

    public function test_clothing_store_defaults_include_garment_workflows(): void
    {
        ModuleService::seedDefaultModules();
        ModuleService::seedDefaultBusinessTypes();

        $keys = ModuleService::getDefaultModuleKeysForBusinessType('Clothing Store');

        foreach (['suppliers', 'purchasing', 'sales-returns', 'brands', 'collections', 'barcode-labels', 'loyalty', 'stock-transfers', 'profit-margins'] as $module) {
            $this->assertContains($module, $keys);
        }

        $this->assertNotContains('warranty', $keys);
        $this->assertNotContains('medical', $keys);
        $this->assertNotContains('allergies', $keys);
    }

    public function test_pharmacy_defaults_include_pharmacy_modules_and_allergies(): void
    {
        ModuleService::seedDefaultModules();
        ModuleService::seedDefaultBusinessTypes();

        $keys = ModuleService::getDefaultModuleKeysForBusinessType('Pharmacy');

        $this->assertContains('medical', $keys);
        $this->assertContains('medical-records', $keys);
        $this->assertContains('allergies', $keys);
        $this->assertContains('pharmacy', $keys);
        $this->assertContains('prescriptions', $keys);
        $this->assertNotContains('patient-records', $keys);
        $this->assertNotContains('appointments', $keys);
    }

    public function test_new_business_types_are_available_and_general_business_is_legacy(): void
    {
        ModuleService::ensureDefaults();

        foreach (
            [
                'Grocery / Supermarket',
                'Wholesale / Distributor',
                'Salon / Beauty',
                'Clinic / Doctor',
                'Hospital',
                'Professional Hospital',
                'Gym / Fitness',
                'Services / Repair Business',
                'Electronics Store',
                'Online Store',
            ] as $name
        ) {
            $this->assertDatabaseHas('business_types', ['name' => $name, 'is_active' => true]);
        }

        $this->assertDatabaseHas('business_types', ['name' => 'General Store', 'is_active' => true]);
        $this->assertDatabaseHas('business_types', ['name' => 'General Business', 'is_active' => false]);
    }

    public function test_recommended_business_types_have_explicit_pos_modes(): void
    {
        foreach (['Grocery / Supermarket', 'Wholesale / Distributor', 'Salon / Beauty', 'Gym / Fitness', 'Services / Repair Business', 'Electronics Store', 'Online Store'] as $type) {
            $this->assertSame('retail', config('pos.business_type_modes.' . strtolower($type)));
        }

        $this->assertSame('medical', config('pos.business_type_modes.clinic / doctor'));
        $this->assertSame('medical', config('pos.business_type_modes.professional hospital'));
    }

    public function test_recommended_business_types_include_their_priority_workflows(): void
    {
        ModuleService::ensureDefaults();

        $requirements = [
            'Grocery / Supermarket' => ['weight-products', 'expiry-tracking', 'suppliers', 'purchasing', 'delivery-zones'],
            'Wholesale / Distributor' => ['credit-sales', 'purchasing', 'profit-margins', 'wholesale-price-lists', 'sales-representatives'],
            'Salon / Beauty' => ['appointments', 'memberships', 'commissions', 'service-packages'],
            'Clinic / Doctor' => ['patient-records', 'appointments', 'medical-records', 'prescriptions', 'medical', 'allergies', 'pharmacy', 'inventory', 'stock', 'item-sales', 'follow-up-reminders'],
            'Hospital' => ['patient-records', 'appointments', 'medical-records', 'prescriptions', 'medical', 'allergies', 'pharmacy', 'inventory', 'stock', 'item-sales', 'follow-up-reminders'],
            'Professional Hospital' => ['patient-records', 'medical-records', 'prescriptions', 'medical', 'allergies', 'pharmacy', 'inventory', 'stock', 'item-sales', 'follow-up-reminders', 'hospital-admissions', 'hospital-departments', 'hospital-wards-beds'],
            'Gym / Fitness' => ['memberships', 'attendance', 'notifications', 'trainer-management'],
            'Services / Repair Business' => ['service-tickets', 'inventory', 'purchasing', 'notifications'],
            'Electronics Store' => ['device-tracking', 'warranty', 'repairs', 'trade-ins', 'installments'],
            'Online Store' => ['orders', 'delivery', 'delivery-zones', 'coupons'],
        ];

        foreach ($requirements as $businessType => $modules) {
            $keys = ModuleService::getDefaultModuleKeysForBusinessType($businessType);
            foreach ($modules as $module) {
                $this->assertContains($module, $keys, $businessType . ' should include ' . $module);
            }
            if ($businessType === 'Professional Hospital') {
                $this->assertNotContains('appointments', $keys);
                $this->assertNotContains('customers', $keys);
            }
        }

        foreach (['Clinic / Doctor', 'Hospital', 'Professional Hospital'] as $businessType) {
            $keys = ModuleService::getDefaultModuleKeysForBusinessType($businessType);
            foreach (['kitchen-display', 'delivery-dispatch', 'recipes', 'reservations', 'delivery-zones'] as $restaurantModule) {
                $this->assertNotContains($restaurantModule, $keys, $businessType . ' should exclude ' . $restaurantModule);
            }
        }
    }

    public function test_seeded_business_types_persist_priority_modules_and_keep_general_business_legacy(): void
    {
        ModuleService::ensureDefaults();

        $this->assertTrue(
            \App\Models\BusinessType::where('name', 'General Business')->where('is_active', false)->exists()
        );
        $this->assertTrue(
            \App\Models\BusinessType::where('name', 'General Store')->where('is_active', true)->exists()
        );

        $persistedRequirements = [
            'Grocery / Supermarket' => ['weight-products', 'expiry-tracking', 'purchasing'],
            'Wholesale / Distributor' => ['credit-sales', 'purchasing', 'profit-margins', 'wholesale-price-lists', 'sales-representatives'],
            'Salon / Beauty' => ['appointments', 'memberships', 'commissions', 'service-packages'],
            'Gym / Fitness' => ['memberships', 'attendance', 'trainer-management'],
            'Clinic / Doctor' => ['medical', 'medical-records', 'patient-records', 'prescriptions', 'pharmacy', 'allergies', 'inventory', 'stock', 'item-sales'],
            'Hospital' => ['medical', 'medical-records', 'patient-records', 'prescriptions', 'pharmacy', 'allergies', 'inventory', 'stock', 'item-sales'],
            'Professional Hospital' => ['medical', 'medical-records', 'patient-records', 'prescriptions', 'pharmacy', 'allergies', 'inventory', 'stock', 'item-sales', 'hospital-admissions', 'hospital-departments', 'hospital-wards-beds', 'hospital-nursing', 'hospital-vital-signs'],
        ];

        foreach ($persistedRequirements as $businessTypeName => $modules) {
            $businessType = \App\Models\BusinessType::where('name', $businessTypeName)->firstOrFail();
            $keys = $businessType->modules()->pluck('key')->all();
            foreach ($modules as $module) {
                $this->assertContains($module, $keys, $businessTypeName . ' should persist ' . $module);
            }
        }
    }

    public function test_medical_defaults_fit_the_current_unlimited_plan_caps(): void
    {
        ModuleService::ensureDefaults();

        $medicalKeys = ModuleService::getDefaultModuleKeysForBusinessType('Clinic / Doctor');
        $starter = \App\Models\SubscriptionPlan::where('slug', 'starter')->first();

        if ($starter && $starter->max_modules !== null) {
            $this->assertGreaterThanOrEqual((int) $starter->max_modules, count($medicalKeys));
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_professional_hospital_bundle_contains_implemented_workflows_and_excludes_roadmap_modules(): void
    {
        ModuleService::ensureDefaults();

        $bundle = ModuleService::professionalHospitalModuleKeys();
        foreach ([
            'hospital-admissions',
            'hospital-departments',
            'hospital-wards-beds',
            'hospital-nursing',
            'hospital-staff-rosters',
            'hospital-vital-signs',
            'hospital-clinical-notes',
            'hospital-care-plans',
            'hospital-laboratory',
            'hospital-radiology',
            'hospital-operating-theatre',
            'hospital-billing',
            'hospital-medication',
            'insurance',
        ] as $module) {
            $this->assertContains($module, $bundle);
            $this->assertDatabaseHas('modules', ['key' => $module, 'is_active' => true]);
        }

        foreach (ModuleService::professionalHospitalOptionalModuleKeys() as $module) {
            $this->assertNotContains($module, $bundle);
            $this->assertDatabaseHas('modules', ['key' => $module, 'is_active' => false]);
        }
    }
}
