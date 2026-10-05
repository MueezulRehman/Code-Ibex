<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ModuleService;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Demo business: Amjd Karyana Store (General Store / Karyana shop).
 *
 * Follows the same pattern as AlMajidAryansStoreSeeder, but is its own
 * independent business (own slug, own manager login, own catalog) so it
 * can be used side-by-side with al-barkat-general-store / al-majid-aryans-store
 * without colliding on data.
 */
class AmjdKaryanaStoreSeeder extends Seeder
{
    public function run(): void
    {
        ModuleService::ensureDefaults();

        $type = BusinessType::where('name', 'General Store')->first()
            ?? BusinessType::where('name', 'General Business')->first()
            ?? BusinessType::where('name', 'Retail / Shop')->first()
            ?? BusinessType::first();

        $modules = $type
            ? ModuleService::getDefaultModuleKeysForBusinessType($type)
            : ['pos', 'menu', 'categories', 'stock', 'inventory', 'customers', 'cashbook', 'expenses', 'reports'];

        $restaurant = Restaurant::firstOrCreate(
            ['slug' => 'amjd-karyana-store'],
            [
                'name' => 'Amjd Karyana Store',
                'email' => 'manager@amjdkaryana.example',
                'phone' => '03215551234',
                'address' => 'Main Bazaar, Karyana Market',
                'business_type_id' => $type?->id,
                'status' => 'active',
                'plan' => 'basic',
                'enabled_modules' => $modules,
            ]
        );

        $manager = User::firstOrNew(['phone' => '03215551234']);
        if (! $manager->exists) {
            $manager->fill([
                'name' => 'Amjd Karyana Manager',
                'email' => 'manager@amjdkaryana.example',
                'role' => 'admin',
                'restaurant_id' => $restaurant->id,
                'password' => Hash::make(Str::random(48)),
                'is_active' => true,
                'module_access' => null,
            ])->save();
        }

        // Refresh (not create-once) subscription period every seed run —
        // see FoodClinicSeeder/DatabaseSeeder fix: firstOrCreate leaves a
        // stale expired subscription untouched on reseed.
        $plan = SubscriptionPlan::where('slug', 'starter')->first() ?? SubscriptionPlan::first();
        RestaurantSubscription::firstOrCreate(
            ['restaurant_id' => $restaurant->id],
            [
                'subscription_plan_id' => $plan?->id,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'auto_renew' => true,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]
        );

        if (! $restaurant->hasTenantDatabase()) {
            app(TenantProvisioner::class)->provision($restaurant, false);
            $restaurant->refresh();
        }

        Tenancy::runFor($restaurant, function () use ($restaurant): void {
            $categories = [
                ['name' => 'Rice & Grains', 'slug' => 'rice-grains', 'icon' => '🌾', 'sort_order' => 1],
                ['name' => 'Pulses (Daal)', 'slug' => 'pulses-daal', 'icon' => '🫘', 'sort_order' => 2],
                ['name' => 'Oils & Ghee', 'slug' => 'oils-ghee', 'icon' => '🫗', 'sort_order' => 3],
                ['name' => 'Spices', 'slug' => 'spices', 'icon' => '🌶️', 'sort_order' => 4],
                ['name' => 'Tea & Beverages', 'slug' => 'tea-beverages', 'icon' => '🍵', 'sort_order' => 5],
                ['name' => 'Bakery & Snacks', 'slug' => 'bakery-snacks', 'icon' => '🍪', 'sort_order' => 6],
                ['name' => 'Dairy & Eggs', 'slug' => 'dairy-eggs', 'icon' => '🥚', 'sort_order' => 7],
                ['name' => 'Household Items', 'slug' => 'household-items', 'icon' => '🧴', 'sort_order' => 8],
                ['name' => 'Vegetables', 'slug' => 'vegetables', 'icon' => '🥬', 'sort_order' => 9],
                ['name' => 'Fruits', 'slug' => 'fruits', 'icon' => '🍎', 'sort_order' => 10],
            ];

            $catModels = [];
            foreach ($categories as $cat) {
                $slugBase = $cat['slug'] ?? Str::slug($cat['name']);
                $desiredSlug = $slugBase.'-amjd';

                // If a category with this restaurant+slug exists, use it.
                $existing = Category::where('restaurant_id', $restaurant->id)->where('slug', $desiredSlug)->first();
                if ($existing) {
                    $catModels[$cat['slug']] = $existing;

                    continue;
                }

                // Ensure global uniqueness of slug by appending a counter if needed.
                $slug = $desiredSlug;
                $counter = 2;
                while (Str::slug($slug) && DB::table('categories')->where('slug', $slug)->exists()) {
                    $slug = $desiredSlug.'-'.$counter;
                    $counter++;
                }

                $insertData = [
                    'restaurant_id' => $restaurant->id,
                    'slug' => $slug,
                    'name' => $cat['name'],
                    'sort_order' => $cat['sort_order'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('categories', 'icon')) {
                    $insertData['icon'] = $cat['icon'];
                }

                try {
                    DB::table('categories')->insert($insertData);
                } catch (\Exception $e) {
                    // Fallback: try to find any category with desired slug, else continue
                }

                $catModels[$cat['slug']] = Category::where('restaurant_id', $restaurant->id)->where('slug', $slug)->first();
            }

            $items = [
                // ---------------- Rice & Grains ----------------
                ['cat' => 'rice-grains', 'name' => 'Super Basmati Rice', 'price' => 320, 'cost_price' => 270, 'unit' => 'kg', 'sku' => 'AKS-RICE-001', 'stock' => 200, 'low' => 20],
                ['cat' => 'rice-grains', 'name' => 'Broken Basmati Rice', 'price' => 220, 'cost_price' => 180, 'unit' => 'kg', 'sku' => 'AKS-RICE-002', 'stock' => 150, 'low' => 15],
                ['cat' => 'rice-grains', 'name' => 'Wheat Flour (Atta) 10kg', 'price' => 1150, 'cost_price' => 980, 'unit' => 'bag', 'sku' => 'AKS-ATTA-001', 'stock' => 40, 'low' => 8],
                ['cat' => 'rice-grains', 'name' => 'Fine Flour (Maida) 1kg', 'price' => 140, 'cost_price' => 110, 'unit' => 'kg', 'sku' => 'AKS-MAIDA-001', 'stock' => 60, 'low' => 10],
                ['cat' => 'rice-grains', 'name' => 'Corn (Makai)', 'price' => 180, 'cost_price' => 145, 'unit' => 'kg', 'sku' => 'AKS-CORN-001', 'stock' => 50, 'low' => 10],

                // ---------------- Pulses (Daal) ----------------
                ['cat' => 'pulses-daal', 'name' => 'Chana Daal', 'price' => 320, 'cost_price' => 270, 'unit' => 'kg', 'sku' => 'AKS-DAAL-001', 'stock' => 80, 'low' => 12],
                ['cat' => 'pulses-daal', 'name' => 'Masoor Daal', 'price' => 300, 'cost_price' => 250, 'unit' => 'kg', 'sku' => 'AKS-DAAL-002', 'stock' => 70, 'low' => 12],
                ['cat' => 'pulses-daal', 'name' => 'Moong Daal', 'price' => 340, 'cost_price' => 285, 'unit' => 'kg', 'sku' => 'AKS-DAAL-003', 'stock' => 60, 'low' => 10],
                ['cat' => 'pulses-daal', 'name' => 'Maash Daal', 'price' => 400, 'cost_price' => 340, 'unit' => 'kg', 'sku' => 'AKS-DAAL-004', 'stock' => 45, 'low' => 8],
                ['cat' => 'pulses-daal', 'name' => 'White Chickpeas (Safed Chana)', 'price' => 380, 'cost_price' => 320, 'unit' => 'kg', 'sku' => 'AKS-CHICKPEA-001', 'stock' => 45, 'low' => 8],

                // ---------------- Oils & Ghee ----------------
                ['cat' => 'oils-ghee', 'name' => 'Sunflower Cooking Oil 1L', 'price' => 630, 'cost_price' => 550, 'unit' => 'bottle', 'sku' => 'AKS-OIL-001', 'stock' => 60, 'low' => 10],
                ['cat' => 'oils-ghee', 'name' => 'Canola Cooking Oil 1L', 'price' => 600, 'cost_price' => 520, 'unit' => 'bottle', 'sku' => 'AKS-OIL-002', 'stock' => 55, 'low' => 10],
                ['cat' => 'oils-ghee', 'name' => 'Desi Ghee 1kg', 'price' => 1850, 'cost_price' => 1600, 'unit' => 'kg', 'sku' => 'AKS-GHEE-001', 'stock' => 25, 'low' => 5],
                ['cat' => 'oils-ghee', 'name' => 'Banaspati Ghee 1kg', 'price' => 620, 'cost_price' => 540, 'unit' => 'kg', 'sku' => 'AKS-GHEE-002', 'stock' => 40, 'low' => 8],

                // ---------------- Spices ----------------
                ['cat' => 'spices', 'name' => 'Red Chilli Powder 200g', 'price' => 195, 'cost_price' => 155, 'unit' => 'pack', 'sku' => 'AKS-SPICE-001', 'stock' => 45, 'low' => 10],
                ['cat' => 'spices', 'name' => 'Turmeric Powder 200g', 'price' => 155, 'cost_price' => 120, 'unit' => 'pack', 'sku' => 'AKS-SPICE-002', 'stock' => 45, 'low' => 10],
                ['cat' => 'spices', 'name' => 'Coriander Powder 200g', 'price' => 145, 'cost_price' => 110, 'unit' => 'pack', 'sku' => 'AKS-SPICE-003', 'stock' => 40, 'low' => 8],
                ['cat' => 'spices', 'name' => 'Garam Masala 100g', 'price' => 105, 'cost_price' => 78, 'unit' => 'pack', 'sku' => 'AKS-SPICE-004', 'stock' => 50, 'low' => 10],
                ['cat' => 'spices', 'name' => 'Cumin Seeds (Zeera) 200g', 'price' => 230, 'cost_price' => 185, 'unit' => 'pack', 'sku' => 'AKS-SPICE-005', 'stock' => 35, 'low' => 8],
                ['cat' => 'spices', 'name' => 'Salt 800g', 'price' => 65, 'cost_price' => 42, 'unit' => 'pack', 'sku' => 'AKS-SPICE-006', 'stock' => 100, 'low' => 15],

                // ---------------- Tea & Beverages ----------------
                ['cat' => 'tea-beverages', 'name' => 'Black Tea 950g', 'price' => 950, 'cost_price' => 800, 'unit' => 'pack', 'sku' => 'AKS-TEA-001', 'stock' => 30, 'low' => 6],
                ['cat' => 'tea-beverages', 'name' => 'Green Tea 100g', 'price' => 320, 'cost_price' => 260, 'unit' => 'pack', 'sku' => 'AKS-TEA-002', 'stock' => 25, 'low' => 5],
                ['cat' => 'tea-beverages', 'name' => 'Cola 1.5L', 'price' => 165, 'cost_price' => 125, 'unit' => 'pcs', 'sku' => 'AKS-BEV-001', 'stock' => 60, 'low' => 12],
                ['cat' => 'tea-beverages', 'name' => 'Mineral Water 1.5L', 'price' => 90, 'cost_price' => 65, 'unit' => 'pcs', 'sku' => 'AKS-BEV-002', 'stock' => 80, 'low' => 15],

                // ---------------- Bakery & Snacks ----------------
                ['cat' => 'bakery-snacks', 'name' => 'Sliced White Bread', 'price' => 160, 'cost_price' => 120, 'unit' => 'pcs', 'sku' => 'AKS-BAKE-001', 'stock' => 25, 'low' => 6],
                ['cat' => 'bakery-snacks', 'name' => 'Tea Time Biscuits', 'price' => 55, 'cost_price' => 38, 'unit' => 'pack', 'sku' => 'AKS-BAKE-002', 'stock' => 60, 'low' => 12],
                ['cat' => 'bakery-snacks', 'name' => 'Potato Chips', 'price' => 100, 'cost_price' => 75, 'unit' => 'pack', 'sku' => 'AKS-SNACK-001', 'stock' => 55, 'low' => 12],
                ['cat' => 'bakery-snacks', 'name' => 'Crunchy Corn Snack', 'price' => 60, 'cost_price' => 42, 'unit' => 'pack', 'sku' => 'AKS-SNACK-002', 'stock' => 65, 'low' => 15],

                // ---------------- Dairy & Eggs ----------------
                ['cat' => 'dairy-eggs', 'name' => 'UHT Milk 1L', 'price' => 255, 'cost_price' => 220, 'unit' => 'pcs', 'sku' => 'AKS-DAIRY-001', 'stock' => 50, 'low' => 10],
                ['cat' => 'dairy-eggs', 'name' => 'Eggs (Dozen)', 'price' => 360, 'cost_price' => 300, 'unit' => 'dozen', 'sku' => 'AKS-DAIRY-002', 'stock' => 40, 'low' => 8],
                ['cat' => 'dairy-eggs', 'name' => 'Yogurt 500g', 'price' => 140, 'cost_price' => 110, 'unit' => 'pcs', 'sku' => 'AKS-DAIRY-003', 'stock' => 30, 'low' => 6],

                // ---------------- Household Items ----------------
                ['cat' => 'household-items', 'name' => 'Laundry Detergent 1kg', 'price' => 480, 'cost_price' => 400, 'unit' => 'pack', 'sku' => 'AKS-HH-001', 'stock' => 35, 'low' => 8],
                ['cat' => 'household-items', 'name' => 'Dishwash Bar', 'price' => 60, 'cost_price' => 45, 'unit' => 'pcs', 'sku' => 'AKS-HH-002', 'stock' => 60, 'low' => 12],
                ['cat' => 'household-items', 'name' => 'Toilet Cleaner 500ml', 'price' => 320, 'cost_price' => 260, 'unit' => 'bottle', 'sku' => 'AKS-HH-003', 'stock' => 30, 'low' => 8],
                ['cat' => 'household-items', 'name' => 'Bathing Soap', 'price' => 100, 'cost_price' => 75, 'unit' => 'pcs', 'sku' => 'AKS-HH-004', 'stock' => 50, 'low' => 10],
            ];

            // ---------------- Vegetables (fresh) ----------------
            $items[] = ['cat' => 'vegetables', 'name' => 'Potato (Aloo) - Loose', 'price' => 60, 'cost_price' => 45, 'unit' => 'kg', 'sku' => 'AKS-VG-001', 'stock' => 200, 'low' => 20];
            $items[] = ['cat' => 'vegetables', 'name' => 'Onion - Loose', 'price' => 80, 'cost_price' => 55, 'unit' => 'kg', 'sku' => 'AKS-VG-002', 'stock' => 180, 'low' => 20];
            $items[] = ['cat' => 'vegetables', 'name' => 'Tomato - Loose', 'price' => 90, 'cost_price' => 60, 'unit' => 'kg', 'sku' => 'AKS-VG-003', 'stock' => 150, 'low' => 15];
            $items[] = ['cat' => 'vegetables', 'name' => 'Carrot - Loose', 'price' => 120, 'cost_price' => 85, 'unit' => 'kg', 'sku' => 'AKS-VG-004', 'stock' => 100, 'low' => 10];

            // ---------------- Fruits (fresh) ----------------
            $items[] = ['cat' => 'fruits', 'name' => 'Apple (Local) - Loose', 'price' => 220, 'cost_price' => 160, 'unit' => 'kg', 'sku' => 'AKS-FR-001', 'stock' => 60, 'low' => 8];
            $items[] = ['cat' => 'fruits', 'name' => 'Orange - Loose', 'price' => 180, 'cost_price' => 130, 'unit' => 'kg', 'sku' => 'AKS-FR-002', 'stock' => 70, 'low' => 10];
            $items[] = ['cat' => 'fruits', 'name' => 'Mango (Seasonal) - Loose', 'price' => 300, 'cost_price' => 200, 'unit' => 'kg', 'sku' => 'AKS-FR-003', 'stock' => 40, 'low' => 5];
            $items[] = ['cat' => 'fruits', 'name' => 'Grapes - Loose', 'price' => 260, 'cost_price' => 180, 'unit' => 'kg', 'sku' => 'AKS-FR-004', 'stock' => 45, 'low' => 6];

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
                    'description' => 'Sold per '.$item['unit'].' at Amjd Karyana Store',
                ];

                if (Schema::hasColumn('menu_items', 'sku')) {
                    $data['sku'] = $item['sku'];
                }
                if (Schema::hasColumn('menu_items', 'unit')) {
                    $data['unit'] = $item['unit'];
                }
                if (Schema::hasColumn('menu_items', 'cost_price')) {
                    $data['cost_price'] = $item['cost_price'];
                }
                if (Schema::hasColumn('menu_items', 'track_stock')) {
                    $data['track_stock'] = true;
                }
                if (Schema::hasColumn('menu_items', 'stock_quantity')) {
                    $data['stock_quantity'] = $item['stock'];
                }
                if (Schema::hasColumn('menu_items', 'low_stock_threshold')) {
                    $data['low_stock_threshold'] = $item['low'];
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

        $this->command?->info('Amjd Karyana Store ready.');
        $this->command?->info('Slug: /amjd-karyana-store');
    }
}
