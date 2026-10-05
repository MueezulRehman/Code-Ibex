<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ModuleService;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Demo business: Al Majid Aryans Store (General Store).
 *
 * @author Mueez Ul Rehman
 */
class AlMajidAryansStoreSeeder extends Seeder
{
    public function run(): void
    {
        ModuleService::ensureDefaults();

        $type = BusinessType::where('name', 'General Store')->first()
            ?? BusinessType::where('name', 'Retail / Shop')->first()
            ?? BusinessType::first();

        $modules = $type
            ? ModuleService::getDefaultModuleKeysForBusinessType($type)
            : ['pos', 'menu', 'categories', 'stock', 'inventory', 'customers', 'cashbook', 'expenses', 'reports'];

        $restaurant = Restaurant::firstOrCreate(
            ['slug' => 'al-majid-aryans-store'],
            [
                'name' => 'Al Majid Aryans Store',
                'email' => 'manager@almajid.example',
                'phone' => '03001234567',
                'address' => 'Shop 12, Aryana Market, Lahore',
                'business_type_id' => $type?->id,
                'status' => 'active',
                'plan' => 'basic',
                'enabled_modules' => $modules,
            ]
        );

        $manager = User::firstOrNew(['phone' => '03001234567']);
        if (! $manager->exists) {
            $manager->fill([
                'name' => 'Al Majid Manager',
                'email' => 'manager@almajid.example',
                'role' => 'admin',
                'restaurant_id' => $restaurant->id,
                'password' => Hash::make(Str::random(48)),
                'is_active' => true,
                'module_access' => null,
            ])->save();
        }

        if (! $restaurant->hasTenantDatabase()) {
            app(TenantProvisioner::class)->provision($restaurant, false);
            $restaurant->refresh();
        }

        Tenancy::runFor($restaurant, function () use ($restaurant): void {
            $categories = [
                ['name' => 'Rice & Grains', 'slug' => 'rice-grains', 'sort_order' => 1],
                ['name' => 'Oils & Ghee', 'slug' => 'oils-ghee', 'sort_order' => 2],
                ['name' => 'Spices', 'slug' => 'spices', 'sort_order' => 3],
                ['name' => 'Daily Essentials', 'slug' => 'daily-essentials', 'sort_order' => 4],
            ];

            $catModels = [];
            foreach ($categories as $cat) {
                $attrs = [
                    'name' => $cat['name'],
                    'sort_order' => $cat['sort_order'],
                    'is_active' => true,
                    'restaurant_id' => $restaurant->id,
                ];
                if (Schema::hasColumn('categories', 'slug')) {
                    $attrs['slug'] = $cat['slug'];
                }
                $catModels[$cat['slug']] = Category::firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $cat['name']],
                    $attrs
                );
            }

            $items = [
                ['cat' => 'rice-grains', 'name' => 'Basmati Rice', 'price' => 280, 'unit' => 'kg', 'sku' => 'AM-RICE-1'],
                ['cat' => 'rice-grains', 'name' => 'Wheat Flour (Atta)', 'price' => 120, 'unit' => 'kg', 'sku' => 'AM-ATTA-1'],
                ['cat' => 'oils-ghee', 'name' => 'Cooking Oil', 'price' => 520, 'unit' => 'liter', 'sku' => 'AM-OIL-1'],
                ['cat' => 'oils-ghee', 'name' => 'Desi Ghee', 'price' => 1800, 'unit' => 'kg', 'sku' => 'AM-GHEE-1'],
                ['cat' => 'spices', 'name' => 'Red Chilli Powder', 'price' => 450, 'unit' => 'kg', 'sku' => 'AM-CHILLI-1'],
                ['cat' => 'spices', 'name' => 'Turmeric Powder', 'price' => 380, 'unit' => 'kg', 'sku' => 'AM-HALDI-1'],
                ['cat' => 'daily-essentials', 'name' => 'Sugar', 'price' => 160, 'unit' => 'kg', 'sku' => 'AM-SUGAR-1'],
                ['cat' => 'daily-essentials', 'name' => 'Tea (Black)', 'price' => 900, 'unit' => 'kg', 'sku' => 'AM-TEA-1'],
                ['cat' => 'daily-essentials', 'name' => 'Bananas', 'price' => 180, 'unit' => 'dozen', 'sku' => 'AM-BANANA-1'],
                ['cat' => 'daily-essentials', 'name' => 'Eggs', 'price' => 360, 'unit' => 'dozen', 'sku' => 'AM-EGG-1'],
            ];

            foreach ($items as $item) {
                $category = $catModels[$item['cat']] ?? null;
                if (! $category) {
                    continue;
                }

                $data = [
                    'category_id' => $category->id,
                    'restaurant_id' => $restaurant->id,
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'is_available' => true,
                    'sort_order' => 1,
                    'description' => 'Sold per '.$item['unit'].' at Al Majid Aryans Store',
                ];

                if (Schema::hasColumn('menu_items', 'sku')) {
                    $data['sku'] = $item['sku'];
                }
                if (Schema::hasColumn('menu_items', 'unit')) {
                    $data['unit'] = $item['unit'];
                }
                if (Schema::hasColumn('menu_items', 'cost_price')) {
                    $data['cost_price'] = round($item['price'] * 0.75, 2);
                }

                MenuItem::firstOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'name' => $item['name'],
                    ],
                    $data
                );
            }
        });

        $this->command?->info('Al Majid Aryans Store ready.');
        $this->command?->info('Slug: /al-majid-aryans-store');
    }
}
