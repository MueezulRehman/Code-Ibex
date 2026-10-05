<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Deal;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Topping;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TasteHutSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Taste Hut Restaurant ----------
        $restaurant = Restaurant::firstOrCreate(
            ['slug' => 'tastehut'],
            [
                'name' => 'Taste Hut',
                'email' => 'info@tastehut.pk',
                'phone' => '0349-0751767',
                'address' => 'Kanda, Nain Ranjha, Amir Ranjha Petrolium, Gojra–Qadirabad Road',
                'plan' => 'pro',
                'status' => 'active',
                'customer_template' => 'modern',
                'show_on_homepage' => true,
                'homepage_sort_order' => 1,
                'theme' => [
                    'primary' => '#0D3B26',
                    'secondary' => '#0D3B26',
                    'accent' => '#F4C400',
                    'light' => '#FFF8D6',
                    'tab_background' => '#111111',
                    'tab_text' => '#F8EAA0',
                    'tab_active' => '#F4C400',
                ],
            ]
        );

        $manager = User::firstOrNew(['phone' => '03437751767']);
        if (! $manager->exists) {
            $manager->fill([
                'name' => 'Taste Hut Manager',
                'email' => 'manager@tastehut.com',
                'role' => 'admin',
                'restaurant_id' => $restaurant->id,
                'password' => Hash::make(Str::random(48)),
                'is_active' => true,
                'joined_at' => now(),
            ])->save();
        }

        if (! $restaurant->hasTenantDatabase()) {
            app(TenantProvisioner::class)->provision($restaurant, false);
            $restaurant->refresh();
        }

        // Catalog records belong to the tenant database. Tenancy::runFor()
        // configures and restores the connection around the full operation.
        Tenancy::runFor($restaurant, function () use ($restaurant): void {
            $rid = $restaurant->id;

            // ---------- Categories ----------
            $categoryData = [
                ['name' => 'Regular Pizza',  'slug' => 'regular-pizza-'.$rid,  'icon' => '🍕', 'sort_order' => 1],
                ['name' => 'Special Pizza',  'slug' => 'special-pizza-'.$rid,  'icon' => '🍕', 'sort_order' => 2],
                ['name' => 'Square Pizza',   'slug' => 'square-pizza-'.$rid,   'icon' => '🍕', 'sort_order' => 3],
                ['name' => 'Burgers',        'slug' => 'burgers-'.$rid,        'icon' => '🍔', 'sort_order' => 4],
                ['name' => 'Shawarma',       'slug' => 'shawarma-'.$rid,       'icon' => '🌯', 'sort_order' => 5],
                ['name' => 'Rolls',          'slug' => 'rolls-'.$rid,          'icon' => '🌯', 'sort_order' => 6],
                ['name' => 'Cheese Stick',   'slug' => 'cheese-stick-'.$rid,   'icon' => '🧀', 'sort_order' => 7],
                ['name' => 'Pasta',          'slug' => 'pasta-'.$rid,          'icon' => '🍝', 'sort_order' => 8],
                ['name' => 'Fries',          'slug' => 'fries-'.$rid,          'icon' => '🍟', 'sort_order' => 9],
                ['name' => 'Wings & Nuggets', 'slug' => 'wings-nuggets-'.$rid,  'icon' => '🍗', 'sort_order' => 10],
                ['name' => 'Chicken Piece',  'slug' => 'chicken-piece-'.$rid,  'icon' => '🍗', 'sort_order' => 11],
                ['name' => 'Ice Cream',      'slug' => 'ice-cream-'.$rid,      'icon' => '🍦', 'sort_order' => 12],
            ];

            foreach ($categoryData as $cd) {
                Category::firstOrCreate(
                    ['restaurant_id' => $rid, 'slug' => $cd['slug']],
                    array_merge($cd, ['restaurant_id' => $rid, 'is_active' => true])
                );
            }

            $cat = fn ($name) => Category::where('name', $name)->where('restaurant_id', $rid)->first()->id;
            $seedMenuItem = fn (array $attributes) => MenuItem::firstOrCreate(
                ['restaurant_id' => $rid, 'name' => $attributes['name']],
                $attributes
            );

            // Regular Pizza
            $rSizes = ['Small' => 550, 'Medium' => 800, 'Large' => 1200, 'XL' => 1700];
            foreach (['Chicken Fajitah', 'Chicken Tikkah', 'Hot & Spicy', 'Bar Be Que', 'Cheese Lover', 'Chicken Supreme'] as $n) {
                $i = $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Regular Pizza'), 'name' => $n, 'has_sizes' => true, 'allows_toppings' => true, 'is_available' => true]);
                foreach ($rSizes as $l => $p) {
                    $i->sizes()->firstOrCreate(['size_label' => $l], ['price' => $p]);
                }
            }

            // Special Pizza
            $sSizes = ['Small' => 600, 'Medium' => 900, 'Large' => 1400, 'XL' => 1950];
            foreach (['Crown Crust', 'Malai Boti', 'Behari Kebab', 'Kebab Stuffed', 'Cheese Stuffed', 'TH Special'] as $n) {
                $i = $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Special Pizza'), 'name' => $n, 'has_sizes' => true, 'allows_toppings' => true, 'is_available' => true]);
                foreach ($sSizes as $l => $p) {
                    $i->sizes()->firstOrCreate(['size_label' => $l], ['price' => $p]);
                }
            }

            // Square Pizza
            $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Square Pizza'), 'name' => 'Cheesy Pizza', 'price' => 1700, 'is_available' => true]);
            $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Square Pizza'), 'name' => 'Peri Peri Pizza', 'price' => 1650, 'is_available' => true]);

            // Burgers
            foreach (['Chicken Petty Burger' => 250, 'Zinger Burger' => 300, 'Zinger Cheese Burger' => 400, 'Chapli Kebab Burger' => 300, 'Thunder Burger' => 550, 'Fillet Burger' => 600, 'Zinger Tower Burger' => 650] as $n => $p) {
                $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Burgers'), 'name' => $n, 'price' => $p, 'is_available' => true]);
            }

            // Shawarma
            foreach (['Chicken Shawarma (Small)' => 150, 'Chicken Shawarma (Full)' => 200, 'Zinger Shawarma' => 300, 'Open Shawarma' => 400] as $n => $p) {
                $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Shawarma'), 'name' => $n, 'price' => $p, 'is_available' => true]);
            }

            // Rolls
            foreach (['Chicken Pratha Roll' => 250, 'Zinger Pratha Roll' => 350, 'Kebab Roll' => 350, 'Pizza Roll' => 550] as $n => $p) {
                $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Rolls'), 'name' => $n, 'price' => $p, 'is_available' => true]);
            }

            // Cheese Stick
            $cs = $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Cheese Stick'), 'name' => 'Cheese Stick', 'has_sizes' => true, 'is_available' => true]);
            foreach (['Small' => 650, 'Medium' => 950, 'Full' => 1450] as $l => $p) {
                $cs->sizes()->firstOrCreate(['size_label' => $l], ['price' => $p]);
            }

            // Pasta
            $cp = $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Pasta'), 'name' => 'Chicken Pasta', 'has_sizes' => true, 'is_available' => true]);
            $cp->sizes()->firstOrCreate(['size_label' => 'Small'], ['price' => 450]);
            $cp->sizes()->firstOrCreate(['size_label' => 'Large'], ['price' => 700]);

            $crp = $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Pasta'), 'name' => 'Crunchy Pasta', 'has_sizes' => true, 'is_available' => true]);
            $crp->sizes()->firstOrCreate(['size_label' => 'Small'], ['price' => 550]);
            $crp->sizes()->firstOrCreate(['size_label' => 'Large'], ['price' => 800]);

            // Fries
            $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Fries'), 'name' => 'Regular Fries', 'price' => 300, 'is_available' => true]);
            $lf = $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Fries'), 'name' => 'Loaded Fries', 'has_sizes' => true, 'is_available' => true]);
            $lf->sizes()->firstOrCreate(['size_label' => 'Small'], ['price' => 450]);
            $lf->sizes()->firstOrCreate(['size_label' => 'Large'], ['price' => 750]);

            // Wings & Nuggets
            $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Wings & Nuggets'), 'name' => 'Hot Wings (6pc)', 'price' => 300, 'is_available' => true]);
            $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Wings & Nuggets'), 'name' => 'Nuggets (6pc)', 'price' => 300, 'is_available' => true]);

            // Chicken Piece
            $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Chicken Piece'), 'name' => 'Chicken Piece (2pc)', 'price' => 450, 'is_available' => true]);
            $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Chicken Piece'), 'name' => 'Chicken Piece (4pc)', 'price' => 800, 'is_available' => true]);
            $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Chicken Piece'), 'name' => 'Chicken Piece (8pc)', 'price' => 1500, 'is_available' => true]);

            // Ice Cream
            foreach (["Wall's Bucket 1.4ltr" => 900, 'Cup Mango 100ml' => 150, 'Caramel 800ml' => 700, 'Paddle Pop Cup 60ml' => 120] as $n => $p) {
                $seedMenuItem(['restaurant_id' => $rid, 'category_id' => $cat('Ice Cream'), 'name' => $n, 'price' => $p, 'is_available' => true]);
            }

            // Toppings
            foreach (
                [
                    ['name' => 'Extra Topping (S)', 'price' => 100, 'type' => 'topping'],
                    ['name' => 'Extra Topping (M)', 'price' => 150, 'type' => 'topping'],
                    ['name' => 'Extra Topping (L)', 'price' => 200, 'type' => 'topping'],
                    ['name' => 'Extra Topping (Family)', 'price' => 250, 'type' => 'topping'],
                    ['name' => 'Dip Sauce', 'price' => 50, 'type' => 'sauce'],
                    ['name' => 'Special Sauce', 'price' => 50, 'type' => 'sauce'],
                    ['name' => 'Cheese Slice', 'price' => 40, 'type' => 'extra'],
                ] as $t
            ) {
                Topping::firstOrCreate(['name' => $t['name']], $t);
            }

            // Deals
            $deals = [
                [1, 600,  'Pizza (Small) + NR Drink + Fries'],
                [2, 300,  'Shawarma + NR Drink + Fries'],
                [3, 400,  'Zinger Burger + NR Drink + Fries'],
                [4, 700,  '2 Zinger Burger + NR Drink + Fries'],
                [5, 850,  'Pizza (Small) + Zinger + NR Drink'],
                [6, 1800, 'Pizza (Medium) + 3 Zinger + 1 Litre Drink'],
                [7, 1100, '2 Pizza (Small) + 2 NR Drink'],
                [8, 1300, '2 Zinger Burger + 6 Hot Wings + 6 Nuggets + 1 Litre Drink + Fries'],
                [9, 1250, 'Pizza (Large) + 1 Litre Drink + Fries'],
                [10, 2350, '1 Pizza (Family) + 12 Hot Wings + 1.5 Litre Drink'],
                [11, 1850, '6 Zinger Burger + 1.5 Litre Drink + Fries'],
                [12, 2400, '2 Pizza (Large) + 1.5 Litre Drink'],
                [13, 1850, 'Pizza (Large) + Zinger + Loaded Fries (Small) + 1 Litre Drink'],
                [14, 1000, 'Pasta (Small) + Loaded Fries (Small)'],
                [15, 3499, '2 Pizza (Large) + 2 Zinger Burger + 2 Chicken Piece + 1 Litre Drink'],
            ];
            foreach ($deals as [$num, $price, $desc]) {
                Deal::firstOrCreate([
                    'restaurant_id' => $rid,
                    'name' => "Deal $num",
                ], [
                    'deal_number' => $num,
                    'price' => $price,
                    'description' => $desc,
                    'image' => 'deals/deal'.$num.'.jpg',
                    'is_active' => true,
                ]);
            }
        });
    }
}
