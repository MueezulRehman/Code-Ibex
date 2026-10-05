<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Services\ModuleService;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoBusinessesSeeder extends Seeder
{
    /**
     * @return array<int, array{name: string, slug: string, type: string, email: string, phone: string, address: string}>
     */
    public static function definitions(): array
    {
        return [
            ['name' => 'Taste Hut', 'slug' => 'tastehut', 'type' => 'Restaurant', 'email' => 'info@tastehut.pk', 'phone' => '0349-0751767', 'address' => 'Gojra-Qadirabad Road'],
            ['name' => 'City Pharmacy', 'slug' => 'city-pharmacy', 'type' => 'Medical Store', 'email' => 'citypharmacy@example.com', 'phone' => '03211234567', 'address' => 'Main Boulevard, Lahore'],
            ['name' => 'Al-Barkat General Store', 'slug' => 'al-barkat-general-store', 'type' => 'General Store', 'email' => 'albarkat@example.com', 'phone' => '03331234567', 'address' => 'Model Town, Lahore'],
            ['name' => 'Karachi Broast & Fries', 'slug' => 'karachi-broast', 'type' => 'Fast Food', 'email' => 'karachibroast@example.com', 'phone' => '03441234567', 'address' => 'Tariq Road, Karachi'],
            ['name' => 'Food Clinic', 'slug' => 'food-clinic', 'type' => 'Fast Food', 'email' => 'foodclinic@example.com', 'phone' => '03494371087', 'address' => 'Sahibwal'],
            ['name' => 'Al Majid Aryans Store', 'slug' => 'al-majid-aryans-store', 'type' => 'General Store', 'email' => 'manager@almajid.example', 'phone' => '03001234567', 'address' => 'Shop 12, Aryana Market, Lahore'],
            ['name' => 'Amjd Karyana Store', 'slug' => 'amjd-karyana-store', 'type' => 'General Store', 'email' => 'manager@amjdkaryana.example', 'phone' => '03215551234', 'address' => 'Main Bazaar, Karyana Market'],
            ['name' => 'Professional Hospital Demo', 'slug' => 'professional-hospital-demo', 'type' => 'Professional Hospital', 'email' => 'hospital-demo@example.test', 'phone' => '03000000000', 'address' => 'Synthetic demonstration facility'],
        ];
    }

    /**
     * Create missing demo business records without replacing existing business data.
     *
     * @return array<int, Restaurant>
     */
    public function createBusinesses(): array
    {
        ModuleService::ensureDefaults();
        $plan = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->first();
        $businesses = [];

        foreach (self::definitions() as $definition) {
            $type = BusinessType::where('name', $definition['type'])->where('is_active', true)->first();
            if (! $type) {
                throw new RuntimeException("Active business type [{$definition['type']}] is required to create the [{$definition['name']}] demo.");
            }

            $business = Restaurant::firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'email' => $definition['email'],
                    'phone' => $definition['phone'],
                    'address' => $definition['address'],
                    'business_type_id' => $type->id,
                    'plan' => $plan?->slug ?? 'basic',
                    'status' => 'active',
                    'show_on_homepage' => false,
                    'storefront_enabled' => false,
                    'storefront_module_override' => false,
                    'customer_template' => 'default',
                    'enabled_modules' => ModuleService::getDefaultModuleKeysForBusinessType($type),
                ]
            );

            $changes = [];
            if (! $business->business_type_id) {
                $changes['business_type_id'] = $type->id;
            }
            if (blank($business->plan)) {
                $changes['plan'] = $plan?->slug ?? 'basic';
            }
            if (empty($business->enabled_modules)) {
                $changes['enabled_modules'] = ModuleService::getDefaultModuleKeysForBusinessType($type);
            }
            if ($changes !== []) {
                $business->forceFill($changes)->save();
            }

            if ($plan) {
                RestaurantSubscription::firstOrCreate(
                    ['restaurant_id' => $business->id],
                    [
                        'subscription_plan_id' => $plan->id,
                        'billing_cycle' => 'monthly',
                        'status' => 'active',
                        'auto_renew' => false,
                        'current_period_start' => now(),
                    ]
                );
            }

            $businesses[] = $business;
        }

        return $businesses;
    }
}
