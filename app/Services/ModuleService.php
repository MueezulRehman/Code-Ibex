<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ModuleService
{
    public static function isEnabled(string $moduleKey): bool
    {
        $user = Auth::user();

        if (!$user || !$user->restaurant_id) {
            return false;
        }

        $restaurant = $user->restaurant;
        if (!$restaurant) {
            return false;
        }

        return $restaurant->isModuleEnabled($moduleKey);
    }

    /**
     * Get all enabled modules for current restaurant.
     */
    public static function getEnabled()
    {
        $user = Auth::user();

        if (!$user || !$user->restaurant_id) {
            return collect();
        }

        $restaurant = $user->restaurant;
        if (!$restaurant) {
            return collect();
        }

        return $restaurant->getEnabledModules();
    }

    public static function ensureDefaults(): void
    {
        static::seedDefaultModules();
        static::seedDefaultBusinessTypes();
    }

    /**
     * Seed default modules.
     */
    public static function seedDefaultModules(): void
    {
        $modules = [
            ['name' => 'Orders', 'key' => 'orders', 'description' => 'Online order management', 'sort_order' => 1, 'is_active' => true],
            ['name' => 'POS', 'key' => 'pos', 'description' => 'Point of Sale register system', 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Menu', 'key' => 'menu', 'description' => 'Menu item catalog', 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Categories', 'key' => 'categories', 'description' => 'Menu categories', 'sort_order' => 4, 'is_active' => true],
            ['name' => 'Variants', 'key' => 'variants', 'description' => 'Item variants and options', 'sort_order' => 5, 'is_active' => true],
            ['name' => 'Deals', 'key' => 'deals', 'description' => 'Combo and deal management', 'sort_order' => 6, 'is_active' => true],
            ['name' => 'Business Theme & Settings', 'key' => 'theme', 'description' => 'Business profile, branding, and storefront appearance settings', 'sort_order' => 7, 'is_active' => true],
            ['name' => 'Cashbook', 'key' => 'cashbook', 'description' => 'Cashbook entries', 'sort_order' => 7, 'is_active' => true],
            ['name' => 'Expenses', 'key' => 'expenses', 'description' => 'Business expense tracking', 'sort_order' => 8, 'is_active' => true],
            ['name' => 'HR', 'key' => 'hr', 'description' => 'HR and staff administration', 'sort_order' => 9, 'is_active' => true],
            ['name' => 'Staff', 'key' => 'staff', 'description' => 'Staff management', 'sort_order' => 10, 'is_active' => true],
            ['name' => 'Attendance', 'key' => 'attendance', 'description' => 'Attendance tracking', 'sort_order' => 11, 'is_active' => true],
            ['name' => 'Salary', 'key' => 'salary', 'description' => 'Salary management', 'sort_order' => 12, 'is_active' => true],
            ['name' => 'Inventory', 'key' => 'inventory', 'description' => 'Inventory and stock management', 'sort_order' => 13, 'is_active' => true],
            ['name' => 'Delivery', 'key' => 'delivery', 'description' => 'Delivery tracking and management', 'sort_order' => 14, 'is_active' => true],
            ['name' => 'Reports', 'key' => 'reports', 'description' => 'Sales and inventory reports', 'sort_order' => 15, 'is_active' => true],
            ['name' => 'Feedback', 'key' => 'feedback', 'description' => 'Customer feedback and suggestions', 'sort_order' => 16, 'is_active' => true],
            ['name' => 'Customers', 'key' => 'customers', 'description' => 'Customer list and order history', 'sort_order' => 17, 'is_active' => true],
            ['name' => 'Notifications', 'key' => 'notifications', 'description' => 'Browser and WhatsApp notifications', 'sort_order' => 18, 'is_active' => true],
            ['name' => 'Storefront Notices', 'key' => 'storefront-notices', 'description' => 'Customer-facing storefront messages', 'sort_order' => 18, 'is_active' => true],
            ['name' => 'Tables', 'key' => 'tables', 'description' => 'Table management and table orders', 'sort_order' => 19, 'is_active' => true],
            ['name' => 'Stock', 'key' => 'stock', 'description' => 'Stock adjustment and history', 'sort_order' => 20, 'is_active' => true],
            ['name' => 'Item Sales', 'key' => 'item-sales', 'description' => 'Promotional sales and discounts for menu items', 'sort_order' => 21, 'is_active' => true],
            ['name' => 'Medical', 'key' => 'medical', 'description' => 'Pharmacy and medical-store workflows', 'sort_order' => 21, 'is_active' => true],
            ['name' => 'Medical Records', 'key' => 'medical-records', 'description' => 'Prescription and medical record tracking', 'sort_order' => 22, 'is_active' => true],
            ['name' => 'Hospital Admissions', 'key' => 'hospital-admissions', 'description' => 'Inpatient admission, transfer, and discharge workflows', 'sort_order' => 23, 'is_active' => true],
            ['name' => 'Hospital Departments', 'key' => 'hospital-departments', 'description' => 'Hospital department setup and assignment workflows', 'sort_order' => 24, 'is_active' => true],
            ['name' => 'Hospital Wards & Beds', 'key' => 'hospital-wards-beds', 'description' => 'Ward, room, and bed capacity management', 'sort_order' => 25, 'is_active' => true],
            ['name' => 'Hospital Care Plans', 'key' => 'hospital-care-plans', 'description' => 'Structured inpatient problems, goals, interventions, and nursing progress', 'sort_order' => 26, 'is_active' => true],
            ['name' => 'General Store', 'key' => 'general_store', 'description' => 'Core modules for general-store workflows', 'sort_order' => 23, 'is_active' => true],
            ['name' => 'Pharmacy', 'key' => 'pharmacy', 'description' => 'Core modules for pharmacy workflows', 'sort_order' => 24, 'is_active' => true],
            ['name' => 'Allergies', 'key' => 'allergies', 'description' => 'Customer allergy tracking and warnings', 'sort_order' => 25, 'is_active' => true],
            ['name' => 'Suppliers', 'key' => 'suppliers', 'description' => 'Supplier directory and supplier contacts', 'sort_order' => 26, 'is_active' => true],
            ['name' => 'Purchasing', 'key' => 'purchasing', 'description' => 'Purchase orders and receiving stock from suppliers', 'sort_order' => 27, 'is_active' => true],
            ['name' => 'Sales Returns', 'key' => 'sales-returns', 'description' => 'Customer returns, exchanges, refunds, and returned stock', 'sort_order' => 28, 'is_active' => true],
            ['name' => 'Warranty', 'key' => 'warranty', 'description' => 'Warranty registration, claims, and expiry tracking', 'sort_order' => 29, 'is_active' => true],
            ['name' => 'Repairs', 'key' => 'repairs', 'description' => 'Repair intake, service status, technician assignment, and collection', 'sort_order' => 30, 'is_active' => true],
            ['name' => 'Trade-ins', 'key' => 'trade-ins', 'description' => 'Used device trade-in valuation and customer credit', 'sort_order' => 31, 'is_active' => true],
            ['name' => 'Device Tracking', 'key' => 'device-tracking', 'description' => 'IMEI, serial numbers, warranty, and device lifecycle tracking', 'sort_order' => 32, 'is_active' => true],
            ['name' => 'Installments', 'key' => 'installments', 'description' => 'Installment plans, deposits, schedules, and outstanding payments', 'sort_order' => 32, 'is_active' => true],
            ['name' => 'Brands', 'key' => 'brands', 'description' => 'Product brands and brand-level catalog filtering', 'sort_order' => 33, 'is_active' => true],
            ['name' => 'Collections', 'key' => 'collections', 'description' => 'Clothing collections and seasonal product grouping', 'sort_order' => 34, 'is_active' => true],
            ['name' => 'Barcode Labels', 'key' => 'barcode-labels', 'description' => 'Generate and print product barcode labels', 'sort_order' => 35, 'is_active' => true],
            ['name' => 'Loyalty', 'key' => 'loyalty', 'description' => 'Customer points, rewards, and repeat-purchase benefits', 'sort_order' => 36, 'is_active' => true],
            ['name' => 'Stock Transfers', 'key' => 'stock-transfers', 'description' => 'Move stock between branches or storage locations', 'sort_order' => 37, 'is_active' => true],
            ['name' => 'Profit Margins', 'key' => 'profit-margins', 'description' => 'Cost, revenue, gross profit, and margin analysis', 'sort_order' => 38, 'is_active' => true],
            ['name' => 'Business Theme', 'key' => 'manager-theme', 'description' => 'Manager dashboard appearance and business branding controls', 'sort_order' => 39, 'is_active' => true],
            ['name' => 'Customer Storefront Theme', 'key' => 'customer-theme', 'description' => 'Customer-facing menu, storefront, and notice appearance controls', 'sort_order' => 40, 'is_active' => true],
            ['name' => 'Appointments', 'key' => 'appointments', 'description' => 'Appointment scheduling and calendar management', 'sort_order' => 41, 'is_active' => true],
            ['name' => 'Hospital Nursing', 'key' => 'hospital-nursing', 'description' => 'Nurse assignments for inpatient admissions', 'sort_order' => 41, 'is_active' => true],
            ['name' => 'Hospital Vital Signs', 'key' => 'hospital-vital-signs', 'description' => 'Record inpatient vital signs and observations', 'sort_order' => 42, 'is_active' => true],
            ['name' => 'Hospital Laboratory', 'key' => 'hospital-laboratory', 'description' => 'Inpatient laboratory orders and results', 'sort_order' => 43, 'is_active' => true],
            ['name' => 'Hospital Radiology', 'key' => 'hospital-radiology', 'description' => 'Inpatient radiology orders and results', 'sort_order' => 44, 'is_active' => true],
            ['name' => 'Hospital Operating Theatre', 'key' => 'hospital-operating-theatre', 'description' => 'Operating rooms and inpatient surgery scheduling', 'sort_order' => 45, 'is_active' => true],
            ['name' => 'Hospital Billing', 'key' => 'hospital-billing', 'description' => 'Inpatient charge capture and payment workflow', 'sort_order' => 46, 'is_active' => true],
            ['name' => 'Hospital Medication Administration', 'key' => 'hospital-medication', 'description' => 'Admission-linked inpatient medication orders and administration records', 'sort_order' => 47, 'is_active' => true],
            ['name' => 'Memberships', 'key' => 'memberships', 'description' => 'Membership plans, renewals, and customer status', 'sort_order' => 42, 'is_active' => true],
            ['name' => 'Service Tickets', 'key' => 'service-tickets', 'description' => 'Service jobs, estimates, assignments, and collection status', 'sort_order' => 43, 'is_active' => true],
            ['name' => 'Recipes', 'key' => 'recipes', 'description' => 'Recipe definitions and ingredient consumption', 'sort_order' => 44, 'is_active' => true],
            ['name' => 'Production Batches', 'key' => 'production-batches', 'description' => 'Production batches and finished goods tracking', 'sort_order' => 45, 'is_active' => true],
            ['name' => 'Delivery Zones', 'key' => 'delivery-zones', 'description' => 'Delivery areas, fees, and serviceability rules', 'sort_order' => 46, 'is_active' => true],
            ['name' => 'Coupons', 'key' => 'coupons', 'description' => 'Coupon codes and promotional discounts', 'sort_order' => 47, 'is_active' => true],
            ['name' => 'Weight-based Products', 'key' => 'weight-products', 'description' => 'Products sold by weight or variable quantity', 'sort_order' => 48, 'is_active' => true],
            ['name' => 'Expiry Tracking', 'key' => 'expiry-tracking', 'description' => 'Expiry dates and near-expiry stock alerts', 'sort_order' => 49, 'is_active' => true],
            ['name' => 'Credit Sales', 'key' => 'credit-sales', 'description' => 'Customer credit limits and receivables tracking', 'sort_order' => 50, 'is_active' => true],
            ['name' => 'Commissions', 'key' => 'commissions', 'description' => 'Staff commission rules and earnings', 'sort_order' => 51, 'is_active' => true],
            ['name' => 'Patient Records', 'key' => 'patient-records', 'description' => 'Patient profiles, notes, and clinical history', 'sort_order' => 52, 'is_active' => true],
            ['name' => 'Follow-up Reminders', 'key' => 'follow-up-reminders', 'description' => 'Scheduled customer and patient follow-up reminders', 'sort_order' => 53, 'is_active' => true],
            ['name' => 'Prescriptions', 'key' => 'prescriptions', 'description' => 'Prescription records and dispensing references', 'sort_order' => 53, 'is_active' => true],
            ['name' => 'Reservations', 'key' => 'reservations', 'description' => 'Table reservations, guest capacity, and booking status', 'sort_order' => 54, 'is_active' => true],
            ['name' => 'Kitchen Display', 'key' => 'kitchen-display', 'description' => 'Kitchen tickets, preparation queue, and station status', 'sort_order' => 55, 'is_active' => true],
            ['name' => 'Delivery Dispatch', 'key' => 'delivery-dispatch', 'description' => 'Rider assignment, dispatch status, and delivery handoff', 'sort_order' => 56, 'is_active' => true],
            ['name' => 'Fitting Room', 'key' => 'fitting-room', 'description' => 'Fitting-room sessions and items taken to the fitting room', 'sort_order' => 57, 'is_active' => true],
            ['name' => 'Insurance', 'key' => 'insurance', 'description' => 'Insurance providers, policies, claims, and patient billing support', 'sort_order' => 58, 'is_active' => true],
            ['name' => 'Controlled Medicines', 'key' => 'controlled-medicines', 'description' => 'Controlled medicine approval, dispensing log, and audit trail', 'sort_order' => 59, 'is_active' => true],
            ['name' => 'Custom Fields', 'key' => 'custom-fields', 'description' => 'Business-defined fields for customers, products, and operational records', 'sort_order' => 60, 'is_active' => true],
            ['name' => 'Custom Workflows', 'key' => 'custom-workflows', 'description' => 'Business-defined statuses, transitions, and workflow records', 'sort_order' => 61, 'is_active' => true],
            ['name' => 'Wholesale Price Lists', 'key' => 'wholesale-price-lists', 'description' => 'Customer or tier-specific wholesale pricing', 'sort_order' => 62, 'is_active' => true],
            ['name' => 'Sales Representatives', 'key' => 'sales-representatives', 'description' => 'Assign sales representatives to wholesale customers', 'sort_order' => 63, 'is_active' => true],
            ['name' => 'Service Packages', 'key' => 'service-packages', 'description' => 'Salon services, packages, and prepaid visits', 'sort_order' => 64, 'is_active' => true],
            ['name' => 'Trainer Management', 'key' => 'trainer-management', 'description' => 'Gym trainers and member assignments', 'sort_order' => 65, 'is_active' => true],
        ];

        // 'pos' already covers the register/checkout screen itself; the mode
        // (restaurant menu vs. barcode retail vs. medicine lookup) is decided
        // per business type in Restaurant::getPosMode(), not by a separate module.

        foreach ($modules as $module) {
            Module::updateOrCreate(['key' => $module['key']], $module);
        }
    }

    public static function getDefaultModuleKeysForBusinessType($businessType): array
    {
        if ($businessType instanceof \App\Models\BusinessType) {
            $keys = $businessType->modules()->pluck('key')->toArray();
            return in_array('theme', $keys, true) ? $keys : [...$keys, 'theme'];
        }

        $typeName = trim((string) $businessType);
        $normalizedName = mb_strtolower($typeName);

        $moduleMap = [
            'restaurant' => ['orders', 'pos', 'menu', 'categories', 'variants', 'deals', 'item-sales', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'tables', 'reservations', 'kitchen-display', 'delivery', 'delivery-zones', 'delivery-dispatch', 'recipes', 'production-batches', 'allergies'],
            'fast food' => ['orders', 'pos', 'menu', 'categories', 'variants', 'deals', 'item-sales', 'cashbook', 'expenses', 'reports', 'feedback', 'customers', 'kitchen-display', 'delivery', 'delivery-zones', 'delivery-dispatch', 'allergies'],
            'retail / shop' => ['pos', 'inventory', 'categories', 'variants', 'item-sales', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'barcode-labels', 'profit-margins'],
            'mobile shop' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'item-sales', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'warranty', 'repairs', 'trade-ins', 'device-tracking', 'installments', 'brands', 'barcode-labels', 'loyalty', 'stock-transfers', 'profit-margins'],
            'mobile store' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'item-sales', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'warranty', 'repairs', 'trade-ins', 'installments', 'brands', 'barcode-labels', 'loyalty', 'stock-transfers', 'profit-margins'],
            'clothing store' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'item-sales', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'brands', 'collections', 'barcode-labels', 'fitting-room', 'loyalty', 'stock-transfers', 'profit-margins'],
            'clothing' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'item-sales', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'brands', 'collections', 'barcode-labels', 'fitting-room', 'loyalty', 'stock-transfers', 'profit-margins'],
            'cafe / bakery' => ['orders', 'pos', 'menu', 'categories', 'variants', 'deals', 'item-sales', 'recipes', 'production-batches', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'tables', 'reservations', 'kitchen-display', 'allergies'],
            'general store' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'item-sales', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'barcode-labels', 'expiry-tracking', 'profit-margins', 'general_store'],
            'grocery / supermarket' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'weight-products', 'expiry-tracking', 'item-sales', 'cashbook', 'expenses', 'reports', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'barcode-labels', 'loyalty', 'stock-transfers', 'delivery-zones'],
            'wholesale / distributor' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'cashbook', 'expenses', 'reports', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'barcode-labels', 'credit-sales', 'profit-margins', 'wholesale-price-lists', 'sales-representatives'],
            'salon / beauty' => ['pos', 'customers', 'appointments', 'memberships', 'service-packages', 'staff', 'attendance', 'salary', 'commissions', 'cashbook', 'expenses', 'reports', 'loyalty'],
            'clinic / doctor' => ['pos', 'customers', 'appointments', 'patient-records', 'medical-records', 'prescriptions', 'medical', 'allergies', 'pharmacy', 'inventory', 'stock', 'item-sales', 'cashbook', 'expenses', 'reports', 'notifications', 'follow-up-reminders'],
            'hospital' => ['pos', 'customers', 'appointments', 'patient-records', 'medical-records', 'prescriptions', 'medical', 'allergies', 'pharmacy', 'inventory', 'stock', 'item-sales', 'cashbook', 'expenses', 'reports', 'notifications', 'follow-up-reminders'],
            'professional hospital' => ['pos', 'patient-records', 'medical-records', 'prescriptions', 'medical', 'allergies', 'pharmacy', 'inventory', 'stock', 'item-sales', 'cashbook', 'expenses', 'reports', 'notifications', 'follow-up-reminders', 'hospital-admissions', 'hospital-departments', 'hospital-wards-beds', 'hospital-nursing', 'hospital-vital-signs', 'hospital-clinical-notes', 'hospital-care-plans', 'hospital-laboratory', 'hospital-radiology', 'hospital-operating-theatre', 'hospital-billing', 'hospital-medication'],
            'gym / fitness' => ['pos', 'customers', 'memberships', 'trainer-management', 'attendance', 'staff', 'salary', 'cashbook', 'expenses', 'reports', 'notifications'],
            'services / repair business' => ['pos', 'customers', 'service-tickets', 'inventory', 'stock', 'suppliers', 'purchasing', 'cashbook', 'expenses', 'reports', 'notifications'],
            'electronics store' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'warranty', 'repairs', 'trade-ins', 'device-tracking', 'installments', 'brands', 'barcode-labels', 'profit-margins'],
            'online store' => ['orders', 'pos', 'inventory', 'categories', 'variants', 'deals', 'customers', 'delivery', 'delivery-zones', 'reports', 'coupons'],
            'medical store' => ['pos', 'inventory', 'categories', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'stock', 'customers', 'suppliers', 'medical', 'medical-records', 'allergies', 'pharmacy', 'expiry-tracking', 'controlled-medicines', 'insurance'],
            'pharmacy' => ['pos', 'inventory', 'categories', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'stock', 'customers', 'suppliers', 'medical', 'medical-records', 'prescriptions', 'allergies', 'pharmacy', 'expiry-tracking', 'controlled-medicines', 'insurance'],
            'other / custom' => ['pos', 'inventory', 'categories', 'variants', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'customers', 'stock', 'feedback', 'custom-fields', 'custom-workflows'],
        ];

        if (array_key_exists($normalizedName, $moduleMap)) {
            return array_values(array_unique([...$moduleMap[$normalizedName], 'theme', 'manager-theme', 'customer-theme']));
        }

        $businessTypeModel = \App\Models\BusinessType::where('name', $typeName)->first();

        if (! $businessTypeModel) {
            return ['theme'];
        }

        $keys = $businessTypeModel->modules()->pluck('key')->toArray();
        return in_array('theme', $keys, true) ? $keys : [...$keys, 'theme'];
    }

    /**
     * Seed default business types with modules.
     */
    public static function seedDefaultBusinessTypes(): void
    {
        $businessTypes = [
            [
                'name' => 'Restaurant',
                'description' => 'Full-service restaurant with dine-in and delivery',
                'modules' => ['orders', 'pos', 'menu', 'categories', 'variants', 'deals', 'recipes', 'production-batches', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'tables', 'allergies'],
            ],
            [
                'name' => 'Fast Food',
                'description' => 'Fast food kitchen for quick takeaway and delivery',
                'modules' => ['orders', 'pos', 'menu', 'categories', 'cashbook', 'expenses', 'reports', 'feedback', 'customers', 'allergies'],
            ],
            [
                'name' => 'Retail / Shop',
                'description' => 'Shop or retail business without a customer storefront',
                'modules' => ['pos', 'inventory', 'categories', 'variants', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers'],
            ],
            [
                'name' => 'Mobile Shop',
                'description' => 'Mobile phones, tablets, chargers, cases, accessories, and repair parts',
                'modules' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'item-sales', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'warranty', 'repairs', 'trade-ins', 'device-tracking', 'installments', 'brands', 'barcode-labels', 'loyalty', 'stock-transfers', 'profit-margins'],
            ],
            [
                'name' => 'Clothing Store',
                'description' => 'Clothing and garments with size, color, stock, and summer/winter seasons',
                'modules' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'item-sales', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'brands', 'collections', 'barcode-labels', 'loyalty', 'stock-transfers', 'profit-margins'],
            ],
            [
                'name' => 'Cafe / Bakery',
                'description' => 'Cafe or bakery with optional storefront ordering',
                'modules' => ['orders', 'pos', 'menu', 'categories', 'variants', 'deals', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'tables', 'allergies'],
            ],
            [
                'name' => 'General Store',
                'description' => 'General store operations without a public storefront',
                'modules' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'general_store'],
            ],
            [
                'name' => 'General Business',
                'description' => 'Legacy name for general store operations',
                'is_active' => false,
                'modules' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'feedback', 'customers', 'general_store'],
            ],
            [
                'name' => 'Grocery / Supermarket',
                'description' => 'Grocery retail with barcode, purchasing, promotions, and delivery zones',
                'modules' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'weight-products', 'expiry-tracking', 'item-sales', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'barcode-labels', 'loyalty', 'stock-transfers', 'delivery-zones'],
            ],
            [
                'name' => 'Wholesale / Distributor',
                'description' => 'Wholesale sales with bulk stock, price lists, purchasing, and receivables workflows',
                'modules' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'barcode-labels', 'credit-sales', 'profit-margins', 'wholesale-price-lists', 'sales-representatives'],
            ],
            [
                'name' => 'Salon / Beauty',
                'description' => 'Service business with appointments, staff, packages, and memberships',
                'modules' => ['pos', 'customers', 'appointments', 'memberships', 'service-packages', 'staff', 'attendance', 'salary', 'commissions', 'cashbook', 'expenses', 'reports', 'loyalty'],
            ],
            [
                'name' => 'Clinic / Doctor',
                'description' => 'Clinic operations with patients, appointments, prescriptions, and billing',
                'modules' => ['pos', 'customers', 'appointments', 'patient-records', 'medical-records', 'prescriptions', 'medical', 'allergies', 'pharmacy', 'inventory', 'stock', 'item-sales', 'cashbook', 'expenses', 'reports', 'notifications', 'follow-up-reminders'],
            ],
            [
                'name' => 'Hospital',
                'description' => 'Hospital operations with patients, appointments, prescriptions, and billing',
                'modules' => ['pos', 'customers', 'appointments', 'patient-records', 'medical-records', 'prescriptions', 'medical', 'allergies', 'pharmacy', 'inventory', 'stock', 'item-sales', 'cashbook', 'expenses', 'reports', 'notifications', 'follow-up-reminders'],
            ],
            [
                'name' => 'Professional Hospital',
                'description' => 'Inpatient hospital operations with separately gated admissions and departments',
                'modules' => ['pos', 'patient-records', 'medical-records', 'prescriptions', 'medical', 'allergies', 'pharmacy', 'inventory', 'stock', 'item-sales', 'cashbook', 'expenses', 'reports', 'notifications', 'follow-up-reminders', 'hospital-admissions', 'hospital-departments', 'hospital-wards-beds', 'hospital-nursing', 'hospital-vital-signs', 'hospital-clinical-notes', 'hospital-care-plans', 'hospital-laboratory', 'hospital-radiology', 'hospital-operating-theatre', 'hospital-billing', 'hospital-medication'],
            ],
            [
                'name' => 'Gym / Fitness',
                'description' => 'Fitness memberships, renewals, attendance, trainers, and payment reminders',
                'modules' => ['pos', 'customers', 'memberships', 'trainer-management', 'attendance', 'staff', 'salary', 'cashbook', 'expenses', 'reports', 'notifications'],
            ],
            [
                'name' => 'Services / Repair Business',
                'description' => 'Service tickets with estimates, technician assignment, parts, and collection status',
                'modules' => ['pos', 'customers', 'service-tickets', 'inventory', 'stock', 'suppliers', 'purchasing', 'cashbook', 'expenses', 'reports', 'notifications'],
            ],
            [
                'name' => 'Electronics Store',
                'description' => 'Electronics retail with warranties, repairs, trade-ins, installments, and accessories',
                'modules' => ['pos', 'inventory', 'categories', 'variants', 'stock', 'customers', 'suppliers', 'purchasing', 'sales-returns', 'warranty', 'repairs', 'trade-ins', 'device-tracking', 'installments', 'brands', 'barcode-labels', 'profit-margins'],
            ],
            [
                'name' => 'Online Store',
                'description' => 'Online catalog, checkout, delivery zones, coupons, and order tracking',
                'modules' => ['orders', 'pos', 'inventory', 'categories', 'variants', 'deals', 'customers', 'delivery', 'delivery-zones', 'reports', 'coupons'],
            ],
            [
                'name' => 'Medical Store',
                'description' => 'Pharmacy / medical store with medicine lookup billing',
                'modules' => ['pos', 'inventory', 'categories', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'stock', 'customers', 'suppliers', 'medical', 'medical-records', 'allergies', 'pharmacy'],
            ],
            [
                'name' => 'Other / Custom',
                'description' => 'Flexible setup for a custom business workflow',
                'modules' => ['pos', 'inventory', 'categories', 'variants', 'cashbook', 'expenses', 'hr', 'staff', 'attendance', 'salary', 'reports', 'customers', 'stock', 'feedback'],
            ],
        ];

        foreach ($businessTypes as $typeData) {
            $modules = $typeData['modules'];
            unset($typeData['modules']);

            $businessType = \App\Models\BusinessType::updateOrCreate(
                ['name' => $typeData['name']],
                array_merge(['is_active' => true, 'sort_order' => 0], $typeData)
            );

            $moduleIds = Module::whereIn('key', $modules)->pluck('id');
            $themeId = Module::where('key', 'theme')->value('id');
            foreach (Module::whereIn('key', ['theme', 'manager-theme', 'customer-theme'])->pluck('id') as $themeModuleId) {
                if (! $moduleIds->contains($themeModuleId)) $moduleIds->push($themeModuleId);
            }
            DB::table('business_type_modules')
                ->where('business_type_id', $businessType->id)
                ->delete();

            if ($moduleIds->isNotEmpty()) {
                DB::table('business_type_modules')->insertOrIgnore(
                    $moduleIds->unique()->values()->map(fn ($moduleId) => [
                        'business_type_id' => $businessType->id,
                        'module_id' => $moduleId,
                    ])->all()
                );
            }
        }

        $additionalDefaults = [
            'Restaurant' => ['reservations', 'kitchen-display', 'delivery', 'delivery-zones', 'delivery-dispatch'],
            'Fast Food' => ['variants', 'deals', 'kitchen-display', 'delivery', 'delivery-zones', 'delivery-dispatch'],
            'Retail / Shop' => ['suppliers', 'purchasing', 'sales-returns', 'barcode-labels', 'profit-margins'],
            'Clothing Store' => ['fitting-room'],
            'Cafe / Bakery' => ['recipes', 'production-batches', 'reservations', 'kitchen-display'],
            'General Store' => ['suppliers', 'purchasing', 'sales-returns', 'barcode-labels', 'expiry-tracking', 'profit-margins'],
            'Medical Store' => ['expiry-tracking', 'controlled-medicines', 'insurance'],
            'Pharmacy' => ['expiry-tracking', 'controlled-medicines', 'insurance'],
            'Hospital' => [],
            'Professional Hospital' => [],
            'Other / Custom' => ['custom-fields', 'custom-workflows'],
        ];

        foreach ($additionalDefaults as $businessTypeName => $moduleKeys) {
            $businessType = \App\Models\BusinessType::where('name', $businessTypeName)->first();
            if (! $businessType) {
                continue;
            }

            $moduleIds = Module::whereIn('key', $moduleKeys)->pluck('id')->unique()->values();

            if ($moduleIds->isNotEmpty()) {
                DB::table('business_type_modules')->insertOrIgnore(
                    $moduleIds->map(fn ($moduleId) => [
                        'business_type_id' => $businessType->id,
                        'module_id' => $moduleId,
                    ])->all()
                );
            }
        }
    }
}
