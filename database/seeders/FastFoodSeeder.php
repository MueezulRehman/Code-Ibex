<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Deal;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FastFoodSeeder extends Seeder
{
    public function run(): void
    {
        $businessType = BusinessType::where('name', 'Fast Food')->first();
        $restaurant = Restaurant::firstOrCreate(
            ['slug' => 'karachi-broast'],
            [
                'name' => 'Karachi Broast & Fries',
                'email' => 'karachibroast@example.com',
                'phone' => '03441234567',
                'address' => 'Tariq Road, Karachi',
                'plan' => 'starter',
                'status' => 'active',
                'business_type_id' => $businessType?->id,
            ]
        );

        if (! $restaurant->hasTenantDatabase()) {
            app(TenantProvisioner::class)->provision($restaurant, false);
            $restaurant->refresh();
        }

        $plan = SubscriptionPlan::where('slug', $restaurant->plan ?: 'starter')->first()
            ?? SubscriptionPlan::where('slug', 'starter')->first();
        if ($plan) {
            RestaurantSubscription::firstOrCreate(
                ['restaurant_id' => $restaurant->id],
                [
                    'subscription_plan_id' => $plan->id,
                    'billing_cycle' => 'monthly',
                    'status' => 'active',
                    'auto_renew' => true,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                ]
            );
        }

        Tenancy::runFor($restaurant, function () use ($restaurant): void {
            DB::connection('tenant')->table('restaurants')->updateOrInsert(
                ['id' => $restaurant->id],
                [
                    'name' => $restaurant->name,
                    'slug' => $restaurant->slug,
                    'status' => $restaurant->status,
                    'created_at' => $restaurant->created_at ?? now(),
                    'updated_at' => now(),
                ]
            );

            $categories = [
                'Broast' => ['icon' => '🍗', 'sort_order' => 1],
                'Burgers' => ['icon' => '🍔', 'sort_order' => 2],
                'Shawarma & Rolls' => ['icon' => '🌯', 'sort_order' => 3],
                'Fries & Sides' => ['icon' => '🍟', 'sort_order' => 4],
                'Drinks' => ['icon' => '🥤', 'sort_order' => 5],
            ];

            $categoryModels = [];
            foreach ($categories as $name => $config) {
                $categoryModels[$name] = Category::on('tenant')->firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $name],
                    [
                        'slug' => Str::slug($restaurant->slug.'-'.$name),
                        'icon' => $config['icon'],
                        'sort_order' => $config['sort_order'],
                        'is_active' => true,
                    ]
                );
            }

            $items = [
                ['Full Broast (4 pcs)', 'Broast', 950, 'Crispy fried chicken, 4 pieces, served with fries and coleslaw.'],
                ['Half Broast (2 pcs)', 'Broast', 550, 'Crispy fried chicken, 2 pieces, served with fries.'],
                ['Zinger Burger', 'Burgers', 450, 'Crispy chicken fillet burger with mayo and lettuce.'],
                ['Beef Burger', 'Burgers', 480, 'Grilled beef patty with cheese, lettuce, and special sauce.'],
                ['Chicken Shawarma', 'Shawarma & Rolls', 250, 'Grilled chicken shawarma roll with garlic sauce and pickles.'],
                ['Beef Seekh Roll', 'Shawarma & Rolls', 280, 'Spiced beef seekh kabab wrapped in roti.'],
                ['French Fries (Regular)', 'Fries & Sides', 200, 'Crispy golden fries, regular portion.'],
                ['Chicken Nuggets (6 pcs)', 'Fries & Sides', 350, 'Crispy chicken nuggets with dip.'],
                ['Fresh Lime', 'Drinks', 120, 'Fresh lime water, sweet or salty.'],
                ['Soft Drink (Regular)', 'Drinks', 100, 'Coke, Pepsi, or Sprite, regular size.'],
            ];

            foreach ($items as [$name, $category, $price, $description]) {
                MenuItem::on('tenant')->firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $name],
                    [
                        'category_id' => $categoryModels[$category]->id,
                        'description' => $description,
                        'price' => $price,
                        'is_available' => true,
                        'sort_order' => 1,
                    ]
                );
            }

            Deal::on('tenant')->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => 'Broast Deal for 2'],
                [
                    'deal_number' => 1,
                    'price' => 1300,
                    'description' => 'Full broast, 2 fries, 2 soft drinks.',
                    'is_active' => true,
                ]
            );
        });
    }
}
