<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Deal;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FoodClinicSeeder extends Seeder
{
    public function run(): void
    {
        $fastFoodType = BusinessType::where('name', 'Fast Food')->first();

        $restaurant = Restaurant::firstOrCreate([
            'slug' => 'food-clinic',
        ], [
            'name' => 'Food Clinic',
            'email' => 'foodclinic@example.com',
            'phone' => '03494371087',
            'address' => 'Sahibwal',
            'plan' => 'basic',
            'status' => 'active',
            'business_type_id' => $fastFoodType?->id,
        ]);
        $restaurant->forceFill(['business_type_id' => $fastFoodType?->id])->save();

        $manager = User::firstOrNew(['email' => 'manager@foodclinic.test']);
        if (! $manager->exists) {
            $manager->fill([
                'phone' => '03494371000',
                'name' => 'Food Clinic Manager',
                'role' => 'admin',
                'restaurant_id' => $restaurant->id,
                'password' => Hash::make(Str::random(48)),
            ])->save();
        }

        RestaurantSubscription::firstOrCreate([
            'restaurant_id' => $restaurant->id,
        ], [
            'subscription_plan_id' => SubscriptionPlan::first()?->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'auto_renew' => true,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        if (! $restaurant->hasTenantDatabase()) {
            app(TenantProvisioner::class)->provision($restaurant, false);
            $restaurant->refresh();
        }

        Tenancy::runFor($restaurant, function () use ($restaurant): void {
            $categories = [
                'Special Pizza' => ['icon' => '🍕', 'sort_order' => 1],
                'Regular Pizza' => ['icon' => '🍕', 'sort_order' => 2],
                'Burgers' => ['icon' => '🍔', 'sort_order' => 3],
                'Nuggets & Wings' => ['icon' => '🍗', 'sort_order' => 4],
                'Cheese Sticks' => ['icon' => '🧀', 'sort_order' => 5],
                'Shawarma & Roll Pratha' => ['icon' => '🌯', 'sort_order' => 6],
                'Fries & Sides' => ['icon' => '🍟', 'sort_order' => 7],
            ];
            $cat = [];
            foreach ($categories as $name => $config) {
                $existing = Category::where('restaurant_id', $restaurant->id)->where('name', $name)->first();
                if ($existing) {
                    $cat[$name] = $existing;
                } else {
                    $cat[$name] = Category::withoutEvents(function () use ($restaurant, $name, $config) {
                        return Category::create([
                            'restaurant_id' => $restaurant->id,
                            'name' => $name,
                            'slug' => Str::slug($restaurant->slug.'-'.$name),
                            'icon' => $config['icon'],
                            'sort_order' => $config['sort_order'],
                            'is_active' => true,
                        ]);
                    });
                }
            }

            // Sized pizzas: Small / Medium / Large / Family
            $specialPizzas = [
                'Food Clinic Special Pizza', 'Crown Crust Pizza', 'Malai Booti Pizza',
                'Kebab Crust Pizza', 'Behari Kebab Pizza',
            ];
            $specialPrices = ['Small' => 600, 'Medium' => 900, 'Large' => 1400, 'Family' => 2000];

            $regularPizzas = [
                'Chicken Tikka Pizza', 'Chicken Fajita Pizza', 'Chicken BBQ Pizza',
                'Cheese Lover Pizza', 'Achari Pizza', 'Vegetable Pizza', 'Hot Spicy Pizza',
            ];
            $regularPrices = ['Small' => 500, 'Medium' => 800, 'Large' => 1200, 'Family' => 1700];

            foreach ([['Special Pizza', $specialPizzas, $specialPrices], ['Regular Pizza', $regularPizzas, $regularPrices]] as [$catName, $names, $prices]) {
                foreach ($names as $name) {
                    $item = MenuItem::firstOrCreate([
                        'restaurant_id' => $restaurant->id,
                        'name' => $name,
                    ], [
                        'category_id' => $cat[$catName]->id,
                        'has_sizes' => true,
                        'is_available' => true,
                        'sort_order' => 0,
                    ]);

                    foreach ($prices as $sizeLabel => $price) {
                        MenuItemSize::firstOrCreate([
                            'menu_item_id' => $item->id,
                            'size_label' => $sizeLabel,
                        ], [
                            'price' => $price,
                            'sort_order' => array_search($sizeLabel, array_keys($prices)),
                        ]);
                    }
                }
            }

            // Simple single-price items
            $simpleItems = [
                ['name' => 'Zinger Burger', 'category' => 'Burgers', 'price' => 300],
                ['name' => 'Zinger Cheese Burger', 'category' => 'Burgers', 'price' => 350],
                ['name' => 'Zinger Tower Burger', 'category' => 'Burgers', 'price' => 600],
                ['name' => 'Chicken Petti Burger', 'category' => 'Burgers', 'price' => 250],
                ['name' => 'Pizza Burger', 'category' => 'Burgers', 'price' => 400],

                ['name' => '6 Pce Wings', 'category' => 'Nuggets & Wings', 'price' => 300],
                ['name' => '12 Pce Wings', 'category' => 'Nuggets & Wings', 'price' => 600],
                ['name' => '12 Pce Wings (Oven Baked)', 'category' => 'Nuggets & Wings', 'price' => 700],
                ['name' => '6 Pce Nuggets', 'category' => 'Nuggets & Wings', 'price' => 300],
                ['name' => '10 Pce Nuggets', 'category' => 'Nuggets & Wings', 'price' => 500],

                ['name' => 'Cheese Sticks (Medium)', 'category' => 'Cheese Sticks', 'price' => 1000],
                ['name' => 'Cheese Sticks (Large)', 'category' => 'Cheese Sticks', 'price' => 1500],

                ['name' => 'Chicken Pratha Roll', 'category' => 'Shawarma & Roll Pratha', 'price' => 300],
                ['name' => 'Zinger Pratha Roll', 'category' => 'Shawarma & Roll Pratha', 'price' => 250],
                ['name' => 'Kebab Pratha Roll', 'category' => 'Shawarma & Roll Pratha', 'price' => 300],
                ['name' => 'Pizza Pratha', 'category' => 'Shawarma & Roll Pratha', 'price' => 500],
                ['name' => 'Chicken Shawarma (Small)', 'category' => 'Shawarma & Roll Pratha', 'price' => 150],
                ['name' => 'Chicken Shawarma (Large)', 'category' => 'Shawarma & Roll Pratha', 'price' => 200],
                ['name' => 'Zinger Shawarma', 'category' => 'Shawarma & Roll Pratha', 'price' => 300],
                ['name' => 'Zinger Cheese Shawarma', 'category' => 'Shawarma & Roll Pratha', 'price' => 250],

                ['name' => 'Regular Fries', 'category' => 'Fries & Sides', 'price' => 200],
                ['name' => 'Large Fries', 'category' => 'Fries & Sides', 'price' => 300],
                ['name' => 'Family Fries', 'category' => 'Fries & Sides', 'price' => 400],
                ['name' => 'Loaded Fries (Small)', 'category' => 'Fries & Sides', 'price' => 400],
                ['name' => 'Loaded Fries (Large)', 'category' => 'Fries & Sides', 'price' => 700],
                ['name' => 'Pasta (Small)', 'category' => 'Fries & Sides', 'price' => 400],
                ['name' => 'Pasta (Large)', 'category' => 'Fries & Sides', 'price' => 700],
            ];

            foreach ($simpleItems as $data) {
                MenuItem::firstOrCreate([
                    'restaurant_id' => $restaurant->id,
                    'name' => $data['name'],
                ], [
                    'category_id' => $cat[$data['category']]->id,
                    'price' => $data['price'],
                    'has_sizes' => false,
                    'is_available' => true,
                ]);
            }

            // "We Charming Deals" — deal_number 1-15, exactly as on the menu board
            $deals = [
                ['n' => 1, 'name' => '2 Large Pizza + 1.5 Ltr Bottle', 'price' => 2349, 'desc' => '2 Large Pizzas with a 1.5 litre bottle.'],
                ['n' => 2, 'name' => '1 Large Pizza + Zinger Burger + 6 Pce Wings + 1.5 Ltr Bottle', 'price' => 1849, 'desc' => '1 Large Pizza, 1 Zinger Burger, 6 pc wings, 1.5 litre bottle.'],
                ['n' => 3, 'name' => '1 Small Pizza + Zinger Burger + Fries + 1 Ltr Bottle', 'price' => 1049, 'desc' => '1 Small Pizza, 1 Zinger Burger, 1 fries, 1 litre bottle.'],
                ['n' => 4, 'name' => 'Zinger Burger + Fries + 1 Ltr Bottle', 'price' => 449, 'desc' => '1 Zinger Burger, fries, and a 1 litre bottle.'],
                ['n' => 5, 'name' => '1 Medium Pizza + 10 Pce Wings + 1 Ltr Bottle', 'price' => 1349, 'desc' => '1 Medium Pizza, 10 pc wings, 1 litre bottle.'],
                ['n' => 6, 'name' => '1 Large Pizza + 10 Pce Wings + 1.5 Ltr Bottle', 'price' => 1749, 'desc' => '1 Large Pizza, 10 pc wings, 1.5 litre bottle.'],
                ['n' => 7, 'name' => '2 Zinger Burgers + Chicken Shawarma + 6 Pce Wings + 1 Ltr Bottle', 'price' => 1349, 'desc' => '2 Zinger Burgers, 1 chicken shawarma, 6 pc wings, 1 litre bottle.'],
                ['n' => 8, 'name' => '1 Small Pizza + 1 Nr Bottle', 'price' => 549, 'desc' => '1 Small Pizza with a regular bottle.'],
                ['n' => 9, 'name' => '1 Medium Pizza + 3 Zinger Burgers + 1 Ltr Bottle', 'price' => 1749, 'desc' => '1 Medium Pizza, 3 Zinger Burgers, 1 litre bottle.'],
                ['n' => 10, 'name' => '2 Zinger Burgers + 6 Pce Nuggets + 6 Pce Wings + 1 Ltr Bottle', 'price' => 1249, 'desc' => '2 Zinger Burgers, 6 pc nuggets, 6 pc wings, 1 litre bottle.'],
                ['n' => 11, 'name' => '2 Large Pizza + 2 Zinger Burger + 12 Pce Hot Wings + Fries + 1.5 Ltr Bottle', 'price' => 3599, 'desc' => '2 Large Pizzas, 2 Zinger Burgers, 12 pc hot wings, fries, 1.5 litre bottle.'],
                ['n' => 12, 'name' => '1 Family Pizza + 12 Hot Wings + Fries + 1.5 Ltr Bottle', 'price' => 2499, 'desc' => '1 Family Pizza, 12 hot wings, fries, 1.5 litre bottle.'],
                ['n' => 13, 'name' => '1 Small Pizza + 6 Nuggets + 6 Pce Wings + 1 Ltr Bottle', 'price' => 1199, 'desc' => '1 Small Pizza, 6 nuggets, 6 pc wings, 1 litre bottle.'],
                ['n' => 14, 'name' => '1 Large Pizza + 2 Zinger Burger + Fries + 1.5 Ltr Bottle', 'price' => 1999, 'desc' => '1 Large Pizza, 2 Zinger Burgers, fries, 1.5 litre bottle.'],
                ['n' => 15, 'name' => '1 Medium Pizza + 2 Zinger Burger + Fries + 1 Liter Drink', 'price' => 1599, 'desc' => '1 Medium Pizza, 2 Zinger Burgers, fries, 1 litre drink.'],
            ];

            foreach ($deals as $deal) {
                Deal::firstOrCreate([
                    'restaurant_id' => $restaurant->id,
                    'deal_number' => $deal['n'],
                ], [
                    'name' => $deal['name'],
                    'price' => $deal['price'],
                    'description' => $deal['desc'],
                    'is_active' => true,
                ]);
            }
        });
    }
}
