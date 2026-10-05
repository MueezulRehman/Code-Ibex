<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PakistaniGeneralStoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'al-barkat-general-store')->first();

        if (! $restaurant) {
            throw new \RuntimeException('Create the Al-Barkat General Store demo business before seeding its catalog.');
        }

        if (! $restaurant->hasTenantDatabase()) {
            app(TenantProvisioner::class)->provision($restaurant, false);
            $restaurant->refresh();
        }

        Tenancy::runFor($restaurant, function () use ($restaurant): void {
            $categories = [
                'Groceries' => ['icon' => '🛒', 'sort_order' => 1],
                'Beverages' => ['icon' => '🥤', 'sort_order' => 2],
                'Frozen' => ['icon' => '🧊', 'sort_order' => 3],
                'Household' => ['icon' => '🏠', 'sort_order' => 4],
                'Snacks' => ['icon' => '🍪', 'sort_order' => 5],
                'Personal Care' => ['icon' => '🧴', 'sort_order' => 6],
                'Bakery' => ['icon' => '🥐', 'sort_order' => 7],
                'Spices & Rice' => ['icon' => '🌶️', 'sort_order' => 8],
                'Fruits' => ['icon' => '🍎', 'sort_order' => 9],
                'Vegetables' => ['icon' => '🥬', 'sort_order' => 10],
            ];

            foreach ($categories as $name => $config) {
                Category::firstOrCreate([
                    'restaurant_id' => $restaurant->id,
                    'name' => $name,
                ], [
                    'slug' => Str::slug($restaurant->slug.'-'.$name),
                    'icon' => $config['icon'],
                    'sort_order' => $config['sort_order'],
                    'is_active' => true,
                ]);
            }

            $items = [
                // ---------------- Groceries ----------------
                ['name' => 'Nido Milk Powder', 'category' => 'Groceries', 'price' => 1200, 'cost_price' => 950, 'sku' => 'GS-001', 'unit' => 'pack', 'stock_quantity' => 25, 'low_stock_threshold' => 5, 'description' => 'Trusted milk powder for daily use.'],
                ['name' => 'Tapal Tea', 'category' => 'Groceries', 'price' => 260, 'cost_price' => 220, 'sku' => 'GS-002', 'unit' => 'pack', 'stock_quantity' => 60, 'low_stock_threshold' => 10, 'description' => 'Popular Pakistani tea brand.'],
                ['name' => 'Olpers Milk 1L', 'category' => 'Groceries', 'price' => 260, 'cost_price' => 225, 'sku' => 'GS-003', 'unit' => 'pcs', 'stock_quantity' => 50, 'low_stock_threshold' => 10, 'description' => 'UHT packaged milk, 1 litre.'],
                ['name' => 'Nurpur Milk 1L', 'category' => 'Groceries', 'price' => 255, 'cost_price' => 220, 'sku' => 'GS-004', 'unit' => 'pcs', 'stock_quantity' => 45, 'low_stock_threshold' => 10, 'description' => 'UHT packaged milk, 1 litre.'],
                ['name' => 'Rafhan Corn Flour', 'category' => 'Groceries', 'price' => 220, 'cost_price' => 180, 'sku' => 'GS-005', 'unit' => 'pack', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Corn flour for cooking and baking.'],
                ['name' => 'Kolson Custard Powder', 'category' => 'Groceries', 'price' => 180, 'cost_price' => 150, 'sku' => 'GS-006', 'unit' => 'pack', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Vanilla custard powder mix.'],
                ['name' => 'Shezan Jam', 'category' => 'Groceries', 'price' => 320, 'cost_price' => 260, 'sku' => 'GS-007', 'unit' => 'jar', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Mixed fruit jam, 400g jar.'],
                ['name' => 'Knorr Chicken Cubes', 'category' => 'Groceries', 'price' => 150, 'cost_price' => 120, 'sku' => 'GS-008', 'unit' => 'pack', 'stock_quantity' => 40, 'low_stock_threshold' => 10, 'description' => 'Chicken stock cubes for cooking.'],
                ['name' => 'Nestle Cerelac', 'category' => 'Groceries', 'price' => 780, 'cost_price' => 650, 'sku' => 'GS-009', 'unit' => 'pack', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'description' => 'Infant cereal, wheat and milk.'],
                ['name' => 'Dalda Cooking Oil 1L', 'category' => 'Groceries', 'price' => 620, 'cost_price' => 540, 'sku' => 'GS-010', 'unit' => 'bottle', 'stock_quantity' => 35, 'low_stock_threshold' => 8, 'description' => 'Everyday cooking oil, 1 litre bottle.'],
                ['name' => 'National Sugar 1kg', 'category' => 'Groceries', 'price' => 175, 'cost_price' => 150, 'sku' => 'GS-011', 'unit' => 'pack', 'stock_quantity' => 70, 'low_stock_threshold' => 15, 'description' => 'Refined white sugar, 1kg pack.'],
                ['name' => 'Lipton Chai Latte', 'category' => 'Groceries', 'price' => 340, 'cost_price' => 280, 'sku' => 'GS-012', 'unit' => 'pack', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Instant chai latte mix.'],
                ['name' => 'Haleeb Milk 1L', 'category' => 'Groceries', 'price' => 250, 'cost_price' => 215, 'sku' => 'GS-013', 'unit' => 'pcs', 'stock_quantity' => 40, 'low_stock_threshold' => 10, 'description' => 'UHT packaged milk, 1 litre.'],

                // ---------------- Beverages ----------------
                ['name' => 'Nestle Milo', 'category' => 'Beverages', 'price' => 450, 'cost_price' => 360, 'sku' => 'BV-001', 'unit' => 'jar', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Chocolate malt drink for families.'],
                ['name' => 'Lipton Yellow Label Tea', 'category' => 'Beverages', 'price' => 320, 'cost_price' => 270, 'sku' => 'BV-002', 'unit' => 'pack', 'stock_quantity' => 45, 'low_stock_threshold' => 10, 'description' => 'Classic black tea bags.'],
                ['name' => 'Pakola Bottle 1.5L', 'category' => 'Beverages', 'price' => 150, 'cost_price' => 110, 'sku' => 'BV-003', 'unit' => 'pcs', 'stock_quantity' => 60, 'low_stock_threshold' => 12, 'description' => 'Classic Pakistani ice cream soda.'],
                ['name' => 'Coca-Cola 1.5L', 'category' => 'Beverages', 'price' => 165, 'cost_price' => 125, 'sku' => 'BV-004', 'unit' => 'pcs', 'stock_quantity' => 55, 'low_stock_threshold' => 12, 'description' => 'Soft drink, 1.5 litre bottle.'],
                ['name' => 'Pepsi 1.5L', 'category' => 'Beverages', 'price' => 160, 'cost_price' => 120, 'sku' => 'BV-005', 'unit' => 'pcs', 'stock_quantity' => 55, 'low_stock_threshold' => 12, 'description' => 'Soft drink, 1.5 litre bottle.'],
                ['name' => '7Up 1.5L', 'category' => 'Beverages', 'price' => 160, 'cost_price' => 120, 'sku' => 'BV-006', 'unit' => 'pcs', 'stock_quantity' => 50, 'low_stock_threshold' => 12, 'description' => 'Lemon-lime soft drink, 1.5 litre.'],
                ['name' => 'Sprite 1.5L', 'category' => 'Beverages', 'price' => 160, 'cost_price' => 120, 'sku' => 'BV-007', 'unit' => 'pcs', 'stock_quantity' => 50, 'low_stock_threshold' => 12, 'description' => 'Lemon-lime soft drink, 1.5 litre.'],
                ['name' => 'Fanta 1.5L', 'category' => 'Beverages', 'price' => 160, 'cost_price' => 120, 'sku' => 'BV-008', 'unit' => 'pcs', 'stock_quantity' => 50, 'low_stock_threshold' => 12, 'description' => 'Orange flavoured soft drink, 1.5 litre.'],
                ['name' => 'Nestle Pure Life Water 1.5L', 'category' => 'Beverages', 'price' => 90, 'cost_price' => 65, 'sku' => 'BV-009', 'unit' => 'pcs', 'stock_quantity' => 80, 'low_stock_threshold' => 15, 'description' => 'Bottled mineral water, 1.5 litre.'],
                ['name' => 'Aquafina Water 1.5L', 'category' => 'Beverages', 'price' => 90, 'cost_price' => 65, 'sku' => 'BV-010', 'unit' => 'pcs', 'stock_quantity' => 80, 'low_stock_threshold' => 15, 'description' => 'Bottled purified water, 1.5 litre.'],
                ['name' => 'Rooh Afza 800ml', 'category' => 'Beverages', 'price' => 420, 'cost_price' => 350, 'sku' => 'BV-011', 'unit' => 'bottle', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Classic rose flavoured sherbet syrup.'],
                ['name' => 'Shezan Mango Juice 1L', 'category' => 'Beverages', 'price' => 220, 'cost_price' => 180, 'sku' => 'BV-012', 'unit' => 'pcs', 'stock_quantity' => 35, 'low_stock_threshold' => 8, 'description' => 'Mango flavoured juice, 1 litre carton.'],
                ['name' => 'Tang Orange Drink Powder', 'category' => 'Beverages', 'price' => 250, 'cost_price' => 200, 'sku' => 'BV-013', 'unit' => 'pack', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Instant orange flavoured drink powder.'],

                // ---------------- Frozen ----------------
                ['name' => 'Walls Ice Cream', 'category' => 'Frozen', 'price' => 350, 'cost_price' => 250, 'sku' => 'FZ-001', 'unit' => 'pcs', 'stock_quantity' => 40, 'low_stock_threshold' => 10, 'description' => 'Popular Walls ice cream available in Pakistan.'],
                ['name' => 'K&N\'s Chicken Nuggets', 'category' => 'Frozen', 'price' => 480, 'cost_price' => 400, 'sku' => 'FZ-002', 'unit' => 'pack', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Frozen chicken nuggets pack.'],
                ['name' => 'Frozen Peas 500g', 'category' => 'Frozen', 'price' => 180, 'cost_price' => 140, 'sku' => 'FZ-003', 'unit' => 'pack', 'stock_quantity' => 22, 'low_stock_threshold' => 6, 'description' => 'Frozen green peas, 500g.'],
                ['name' => 'Sufi Frozen Paratha', 'category' => 'Frozen', 'price' => 260, 'cost_price' => 210, 'sku' => 'FZ-004', 'unit' => 'pack', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Ready-to-cook frozen paratha, pack of 5.'],
                ['name' => 'Menu Chicken Shami Kabab', 'category' => 'Frozen', 'price' => 420, 'cost_price' => 350, 'sku' => 'FZ-005', 'unit' => 'pack', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'description' => 'Ready-to-fry frozen shami kababs.'],
                ['name' => 'Seatalk Frozen Fish Fillet', 'category' => 'Frozen', 'price' => 650, 'cost_price' => 540, 'sku' => 'FZ-006', 'unit' => 'pack', 'stock_quantity' => 15, 'low_stock_threshold' => 5, 'description' => 'Frozen fish fillet pack.'],
                ['name' => 'Al Safa Chicken Franks', 'category' => 'Frozen', 'price' => 380, 'cost_price' => 310, 'sku' => 'FZ-007', 'unit' => 'pack', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Frozen chicken franks/hot dogs.'],
                ['name' => 'Frozen French Fries 1kg', 'category' => 'Frozen', 'price' => 340, 'cost_price' => 270, 'sku' => 'FZ-008', 'unit' => 'pack', 'stock_quantity' => 28, 'low_stock_threshold' => 6, 'description' => 'Ready-to-fry frozen french fries.'],
                ['name' => 'Big Bird Chicken Wings', 'category' => 'Frozen', 'price' => 520, 'cost_price' => 430, 'sku' => 'FZ-009', 'unit' => 'pack', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'description' => 'Frozen marinated chicken wings.'],
                ['name' => 'Omore Ice Cream', 'category' => 'Frozen', 'price' => 330, 'cost_price' => 240, 'sku' => 'FZ-010', 'unit' => 'pcs', 'stock_quantity' => 35, 'low_stock_threshold' => 8, 'description' => 'Popular local ice cream brand.'],
                ['name' => 'Igloo Ice Cream', 'category' => 'Frozen', 'price' => 320, 'cost_price' => 235, 'sku' => 'FZ-011', 'unit' => 'pcs', 'stock_quantity' => 35, 'low_stock_threshold' => 8, 'description' => 'Classic Pakistani ice cream brand.'],
                ['name' => 'Frozen Mixed Vegetables', 'category' => 'Frozen', 'price' => 190, 'cost_price' => 150, 'sku' => 'FZ-012', 'unit' => 'pack', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'description' => 'Frozen mixed vegetable pack.'],
                ['name' => 'Frozen Sweet Corn', 'category' => 'Frozen', 'price' => 200, 'cost_price' => 160, 'sku' => 'FZ-013', 'unit' => 'pack', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'description' => 'Frozen sweet corn kernels.'],

                // ---------------- Household ----------------
                ['name' => 'Dettol Soap', 'category' => 'Household', 'price' => 120, 'cost_price' => 90, 'sku' => 'HH-001', 'unit' => 'pcs', 'stock_quantity' => 50, 'low_stock_threshold' => 10, 'description' => 'Hygiene soap for everyday use.'],
                ['name' => 'Surf Excel Detergent 1kg', 'category' => 'Household', 'price' => 480, 'cost_price' => 400, 'sku' => 'HH-002', 'unit' => 'pack', 'stock_quantity' => 35, 'low_stock_threshold' => 8, 'description' => 'Laundry detergent powder, 1kg.'],
                ['name' => 'Lifebuoy Handwash 200ml', 'category' => 'Household', 'price' => 220, 'cost_price' => 175, 'sku' => 'HH-003', 'unit' => 'pcs', 'stock_quantity' => 40, 'low_stock_threshold' => 8, 'description' => 'Antibacterial handwash bottle.'],
                ['name' => 'Vim Dishwash Bar', 'category' => 'Household', 'price' => 60, 'cost_price' => 45, 'sku' => 'HH-004', 'unit' => 'pcs', 'stock_quantity' => 60, 'low_stock_threshold' => 12, 'description' => 'Dishwashing bar for tough grease.'],
                ['name' => 'Harpic Toilet Cleaner', 'category' => 'Household', 'price' => 320, 'cost_price' => 260, 'sku' => 'HH-005', 'unit' => 'bottle', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Toilet bowl cleaner, 500ml.'],
                ['name' => 'Colgate Toothpaste', 'category' => 'Household', 'price' => 210, 'cost_price' => 165, 'sku' => 'HH-006', 'unit' => 'pcs', 'stock_quantity' => 50, 'low_stock_threshold' => 10, 'description' => 'Everyday cavity protection toothpaste.'],
                ['name' => 'Ariel Detergent Powder 1kg', 'category' => 'Household', 'price' => 520, 'cost_price' => 440, 'sku' => 'HH-007', 'unit' => 'pack', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Deep clean laundry detergent, 1kg.'],
                ['name' => 'Lizol Floor Cleaner', 'category' => 'Household', 'price' => 380, 'cost_price' => 310, 'sku' => 'HH-008', 'unit' => 'bottle', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Disinfectant floor cleaner, 1L.'],
                ['name' => 'Mr Muscle Glass Cleaner', 'category' => 'Household', 'price' => 280, 'cost_price' => 220, 'sku' => 'HH-009', 'unit' => 'bottle', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'description' => 'Streak-free glass and mirror cleaner.'],
                ['name' => 'Tibet Snow', 'category' => 'Household', 'price' => 140, 'cost_price' => 105, 'sku' => 'HH-010', 'unit' => 'pcs', 'stock_quantity' => 35, 'low_stock_threshold' => 8, 'description' => 'Classic Pakistani cold cream.'],
                ['name' => 'Comfort Fabric Softener', 'category' => 'Household', 'price' => 350, 'cost_price' => 280, 'sku' => 'HH-011', 'unit' => 'bottle', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Long-lasting fragrance fabric softener.'],
                ['name' => 'Brite Detergent Powder', 'category' => 'Household', 'price' => 400, 'cost_price' => 330, 'sku' => 'HH-012', 'unit' => 'pack', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Budget-friendly laundry detergent, 1kg.'],
                ['name' => 'Softlan Fabric Conditioner', 'category' => 'Household', 'price' => 340, 'cost_price' => 270, 'sku' => 'HH-013', 'unit' => 'bottle', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Fabric conditioner for soft clothes.'],

                // ---------------- Snacks ----------------
                ['name' => 'Lays Chips', 'category' => 'Snacks', 'price' => 100, 'cost_price' => 75, 'sku' => 'SN-001', 'unit' => 'pack', 'stock_quantity' => 60, 'low_stock_threshold' => 12, 'description' => 'Classic salted potato chips.'],
                ['name' => 'Kurkure', 'category' => 'Snacks', 'price' => 60, 'cost_price' => 42, 'sku' => 'SN-002', 'unit' => 'pack', 'stock_quantity' => 70, 'low_stock_threshold' => 15, 'description' => 'Crunchy masala corn snack.'],
                ['name' => 'Rio Biscuits', 'category' => 'Snacks', 'price' => 50, 'cost_price' => 35, 'sku' => 'SN-003', 'unit' => 'pack', 'stock_quantity' => 65, 'low_stock_threshold' => 15, 'description' => 'Sandwich cream biscuits.'],
                ['name' => 'Sooper Biscuits', 'category' => 'Snacks', 'price' => 55, 'cost_price' => 38, 'sku' => 'SN-004', 'unit' => 'pack', 'stock_quantity' => 65, 'low_stock_threshold' => 15, 'description' => 'Popular family biscuit pack.'],
                ['name' => 'Candi Biscuits', 'category' => 'Snacks', 'price' => 50, 'cost_price' => 35, 'sku' => 'SN-005', 'unit' => 'pack', 'stock_quantity' => 60, 'low_stock_threshold' => 12, 'description' => 'Sweet coconut biscuits.'],
                ['name' => 'Peek Freans Gluco', 'category' => 'Snacks', 'price' => 45, 'cost_price' => 30, 'sku' => 'SN-006', 'unit' => 'pack', 'stock_quantity' => 70, 'low_stock_threshold' => 15, 'description' => 'Classic glucose biscuits.'],
                ['name' => 'LU Prince Biscuits', 'category' => 'Snacks', 'price' => 55, 'cost_price' => 38, 'sku' => 'SN-007', 'unit' => 'pack', 'stock_quantity' => 60, 'low_stock_threshold' => 12, 'description' => 'Chocolate cream sandwich biscuits.'],
                ['name' => 'Bisconni Chocolatto', 'category' => 'Snacks', 'price' => 60, 'cost_price' => 42, 'sku' => 'SN-008', 'unit' => 'pack', 'stock_quantity' => 55, 'low_stock_threshold' => 12, 'description' => 'Chocolate flavoured cream biscuits.'],
                ['name' => 'Slanty Chips', 'category' => 'Snacks', 'price' => 40, 'cost_price' => 28, 'sku' => 'SN-009', 'unit' => 'pack', 'stock_quantity' => 65, 'low_stock_threshold' => 15, 'description' => 'Spicy corn chip snack.'],
                ['name' => 'Cheetos', 'category' => 'Snacks', 'price' => 100, 'cost_price' => 75, 'sku' => 'SN-010', 'unit' => 'pack', 'stock_quantity' => 45, 'low_stock_threshold' => 10, 'description' => 'Cheese flavoured puffed snack.'],
                ['name' => 'Rollos Jam Filled', 'category' => 'Snacks', 'price' => 50, 'cost_price' => 34, 'sku' => 'SN-011', 'unit' => 'pack', 'stock_quantity' => 40, 'low_stock_threshold' => 10, 'description' => 'Jam filled swiss roll snack cakes.'],
                ['name' => 'Oreo Biscuits', 'category' => 'Snacks', 'price' => 130, 'cost_price' => 95, 'sku' => 'SN-012', 'unit' => 'pack', 'stock_quantity' => 40, 'low_stock_threshold' => 10, 'description' => 'Classic chocolate sandwich cookies.'],
                ['name' => 'Krunchips', 'category' => 'Snacks', 'price' => 80, 'cost_price' => 58, 'sku' => 'SN-013', 'unit' => 'pack', 'stock_quantity' => 45, 'low_stock_threshold' => 10, 'description' => 'Crunchy potato chip snack.'],

                // ---------------- Personal Care ----------------
                ['name' => 'Sensodyne Toothpaste', 'category' => 'Personal Care', 'price' => 380, 'cost_price' => 310, 'sku' => 'PC-001', 'unit' => 'pcs', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Sensitive teeth care toothpaste.'],
                ['name' => 'Head & Shoulders Shampoo', 'category' => 'Personal Care', 'price' => 550, 'cost_price' => 460, 'sku' => 'PC-002', 'unit' => 'bottle', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Anti-dandruff shampoo, 375ml.'],
                ['name' => 'Gillette Razor', 'category' => 'Personal Care', 'price' => 250, 'cost_price' => 190, 'sku' => 'PC-003', 'unit' => 'pcs', 'stock_quantity' => 40, 'low_stock_threshold' => 8, 'description' => 'Disposable shaving razor.'],
                ['name' => 'Nivea Body Lotion', 'category' => 'Personal Care', 'price' => 620, 'cost_price' => 520, 'sku' => 'PC-004', 'unit' => 'bottle', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Moisturizing body lotion, 400ml.'],
                ['name' => 'Fair & Lovely Cream', 'category' => 'Personal Care', 'price' => 260, 'cost_price' => 210, 'sku' => 'PC-005', 'unit' => 'pcs', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Daily face cream.'],
                ['name' => 'Vaseline Petroleum Jelly', 'category' => 'Personal Care', 'price' => 190, 'cost_price' => 150, 'sku' => 'PC-006', 'unit' => 'pcs', 'stock_quantity' => 35, 'low_stock_threshold' => 8, 'description' => 'Skin protecting petroleum jelly, 100ml.'],
                ['name' => 'Lux Soap', 'category' => 'Personal Care', 'price' => 100, 'cost_price' => 75, 'sku' => 'PC-007', 'unit' => 'pcs', 'stock_quantity' => 50, 'low_stock_threshold' => 10, 'description' => 'Fragrant beauty soap bar.'],
                ['name' => 'Safeguard Soap', 'category' => 'Personal Care', 'price' => 105, 'cost_price' => 80, 'sku' => 'PC-008', 'unit' => 'pcs', 'stock_quantity' => 50, 'low_stock_threshold' => 10, 'description' => 'Germ protection soap bar.'],
                ['name' => 'Pears Soap', 'category' => 'Personal Care', 'price' => 130, 'cost_price' => 100, 'sku' => 'PC-009', 'unit' => 'pcs', 'stock_quantity' => 40, 'low_stock_threshold' => 8, 'description' => 'Glycerin soap bar.'],
                ['name' => 'Dove Soap', 'category' => 'Personal Care', 'price' => 150, 'cost_price' => 115, 'sku' => 'PC-010', 'unit' => 'pcs', 'stock_quantity' => 40, 'low_stock_threshold' => 8, 'description' => 'Moisturizing beauty bar.'],
                ['name' => 'Clear Shampoo', 'category' => 'Personal Care', 'price' => 480, 'cost_price' => 390, 'sku' => 'PC-011', 'unit' => 'bottle', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Anti-dandruff shampoo, 375ml.'],
                ['name' => 'Sunsilk Shampoo', 'category' => 'Personal Care', 'price' => 460, 'cost_price' => 375, 'sku' => 'PC-012', 'unit' => 'bottle', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Hair fall control shampoo, 375ml.'],
                ['name' => 'Johnson\'s Baby Powder', 'category' => 'Personal Care', 'price' => 320, 'cost_price' => 260, 'sku' => 'PC-013', 'unit' => 'pcs', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Gentle baby talcum powder.'],

                // ---------------- Bakery ----------------
                ['name' => 'Dawn White Bread', 'category' => 'Bakery', 'price' => 160, 'cost_price' => 120, 'sku' => 'BK-001', 'unit' => 'pcs', 'stock_quantity' => 30, 'low_stock_threshold' => 8, 'description' => 'Fresh sliced white bread loaf.'],
                ['name' => 'Bake Parlor Cake', 'category' => 'Bakery', 'price' => 450, 'cost_price' => 360, 'sku' => 'BK-002', 'unit' => 'pcs', 'stock_quantity' => 15, 'low_stock_threshold' => 4, 'description' => 'Fresh baked whole cake.'],
                ['name' => 'English Biscuit Tea Time', 'category' => 'Bakery', 'price' => 55, 'cost_price' => 38, 'sku' => 'BK-003', 'unit' => 'pack', 'stock_quantity' => 55, 'low_stock_threshold' => 12, 'description' => 'Classic tea time biscuits.'],
                ['name' => 'Sooper Rusk', 'category' => 'Bakery', 'price' => 130, 'cost_price' => 95, 'sku' => 'BK-004', 'unit' => 'pack', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Crunchy toasted rusk pack.'],
                ['name' => 'Kolson Naan Khatai', 'category' => 'Bakery', 'price' => 140, 'cost_price' => 100, 'sku' => 'BK-005', 'unit' => 'pack', 'stock_quantity' => 25, 'low_stock_threshold' => 6, 'description' => 'Traditional naan khatai cookies.'],
                ['name' => 'Shan Bakery Bun', 'category' => 'Bakery', 'price' => 40, 'cost_price' => 28, 'sku' => 'BK-006', 'unit' => 'pcs', 'stock_quantity' => 40, 'low_stock_threshold' => 10, 'description' => 'Soft sweet bakery bun.'],
                ['name' => 'Continental Biscuits Assorted', 'category' => 'Bakery', 'price' => 350, 'cost_price' => 280, 'sku' => 'BK-007', 'unit' => 'pack', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'description' => 'Assorted premium biscuit box.'],
                ['name' => 'Dunkin Donuts Pack', 'category' => 'Bakery', 'price' => 480, 'cost_price' => 400, 'sku' => 'BK-008', 'unit' => 'pack', 'stock_quantity' => 15, 'low_stock_threshold' => 4, 'description' => 'Box of assorted donuts.'],
                ['name' => 'Mithai Assorted Box', 'category' => 'Bakery', 'price' => 650, 'cost_price' => 520, 'sku' => 'BK-009', 'unit' => 'box', 'stock_quantity' => 15, 'low_stock_threshold' => 4, 'description' => 'Assorted traditional sweets box.'],
                ['name' => 'Marie Biscuits', 'category' => 'Bakery', 'price' => 45, 'cost_price' => 30, 'sku' => 'BK-010', 'unit' => 'pack', 'stock_quantity' => 55, 'low_stock_threshold' => 12, 'description' => 'Light tea biscuits.'],
                ['name' => 'Digestive Biscuits', 'category' => 'Bakery', 'price' => 150, 'cost_price' => 115, 'sku' => 'BK-011', 'unit' => 'pack', 'stock_quantity' => 30, 'low_stock_threshold' => 6, 'description' => 'Wholegrain digestive biscuits.'],
                ['name' => 'Coconut Cookies', 'category' => 'Bakery', 'price' => 60, 'cost_price' => 42, 'sku' => 'BK-012', 'unit' => 'pack', 'stock_quantity' => 35, 'low_stock_threshold' => 8, 'description' => 'Sweet coconut flavoured cookies.'],
                ['name' => 'Chocolate Cake Slice', 'category' => 'Bakery', 'price' => 150, 'cost_price' => 110, 'sku' => 'BK-013', 'unit' => 'pcs', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'description' => 'Fresh baked chocolate cake slice.'],

                // ---------------- Spices & Rice ----------------
                ['name' => 'National Basmati Rice 5kg', 'category' => 'Spices & Rice', 'price' => 1850, 'cost_price' => 1600, 'sku' => 'SP-001', 'unit' => 'bag', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'description' => 'Premium basmati rice, 5kg bag.'],
                ['name' => 'Shan Biryani Masala', 'category' => 'Spices & Rice', 'price' => 95, 'cost_price' => 70, 'sku' => 'SP-002', 'unit' => 'pack', 'stock_quantity' => 80, 'low_stock_threshold' => 15, 'description' => 'Popular ready-mix biryani masala.'],
                ['name' => 'Shan Karahi Masala', 'category' => 'Spices & Rice', 'price' => 90, 'cost_price' => 65, 'sku' => 'SP-003', 'unit' => 'pack', 'stock_quantity' => 70, 'low_stock_threshold' => 15, 'description' => 'Chicken karahi spice mix.'],
                ['name' => 'National Chaat Masala', 'category' => 'Spices & Rice', 'price' => 85, 'cost_price' => 60, 'sku' => 'SP-004', 'unit' => 'pack', 'stock_quantity' => 60, 'low_stock_threshold' => 12, 'description' => 'Tangy chaat masala seasoning.'],
                ['name' => 'Mehran Salt 800g', 'category' => 'Spices & Rice', 'price' => 60, 'cost_price' => 40, 'sku' => 'SP-005', 'unit' => 'pack', 'stock_quantity' => 90, 'low_stock_threshold' => 15, 'description' => 'Iodized cooking salt.'],
                ['name' => 'Habib Red Chilli Powder', 'category' => 'Spices & Rice', 'price' => 190, 'cost_price' => 150, 'sku' => 'SP-006', 'unit' => 'pack', 'stock_quantity' => 45, 'low_stock_threshold' => 10, 'description' => 'Ground red chilli powder, 200g.'],
                ['name' => 'Shan Kabab Masala', 'category' => 'Spices & Rice', 'price' => 90, 'cost_price' => 65, 'sku' => 'SP-007', 'unit' => 'pack', 'stock_quantity' => 60, 'low_stock_threshold' => 12, 'description' => 'Seekh kabab spice mix.'],
                ['name' => 'National Garam Masala', 'category' => 'Spices & Rice', 'price' => 100, 'cost_price' => 75, 'sku' => 'SP-008', 'unit' => 'pack', 'stock_quantity' => 55, 'low_stock_threshold' => 12, 'description' => 'Aromatic garam masala blend.'],
                ['name' => 'Mehran Turmeric Powder', 'category' => 'Spices & Rice', 'price' => 150, 'cost_price' => 115, 'sku' => 'SP-009', 'unit' => 'pack', 'stock_quantity' => 45, 'low_stock_threshold' => 10, 'description' => 'Ground turmeric powder, 200g.'],
                ['name' => 'Shan Chicken Tikka Masala', 'category' => 'Spices & Rice', 'price' => 90, 'cost_price' => 65, 'sku' => 'SP-010', 'unit' => 'pack', 'stock_quantity' => 65, 'low_stock_threshold' => 12, 'description' => 'BBQ chicken tikka spice mix.'],
                ['name' => 'National Sabzi Masala', 'category' => 'Spices & Rice', 'price' => 80, 'cost_price' => 58, 'sku' => 'SP-011', 'unit' => 'pack', 'stock_quantity' => 50, 'low_stock_threshold' => 10, 'description' => 'Vegetable curry spice mix.'],
                ['name' => 'Habib Coriander Powder', 'category' => 'Spices & Rice', 'price' => 140, 'cost_price' => 105, 'sku' => 'SP-012', 'unit' => 'pack', 'stock_quantity' => 40, 'low_stock_threshold' => 8, 'description' => 'Ground coriander powder, 200g.'],
                ['name' => 'Shan Bombay Biryani Masala', 'category' => 'Spices & Rice', 'price' => 95, 'cost_price' => 70, 'sku' => 'SP-013', 'unit' => 'pack', 'stock_quantity' => 55, 'low_stock_threshold' => 12, 'description' => 'Bombay-style biryani spice mix.'],
            ];

            // ---------------- Vegetables (fresh) ----------------
            $items[] = ['name' => 'Potato (Aloo) - Loose', 'category' => 'Vegetables', 'price' => 60, 'cost_price' => 45, 'sku' => 'VG-001', 'unit' => 'kg', 'stock_quantity' => 120, 'low_stock_threshold' => 20, 'description' => 'Fresh loose potatoes sold per kg.'];
            $items[] = ['name' => 'Onion - Loose', 'category' => 'Vegetables', 'price' => 80, 'cost_price' => 55, 'sku' => 'VG-002', 'unit' => 'kg', 'stock_quantity' => 100, 'low_stock_threshold' => 20, 'description' => 'Red onions sold per kg.'];
            $items[] = ['name' => 'Tomato - Loose', 'category' => 'Vegetables', 'price' => 90, 'cost_price' => 60, 'sku' => 'VG-003', 'unit' => 'kg', 'stock_quantity' => 90, 'low_stock_threshold' => 15, 'description' => 'Fresh tomatoes sold per kg.'];
            $items[] = ['name' => 'Carrot - Loose', 'category' => 'Vegetables', 'price' => 120, 'cost_price' => 85, 'sku' => 'VG-004', 'unit' => 'kg', 'stock_quantity' => 60, 'low_stock_threshold' => 10, 'description' => 'Fresh carrots sold per kg.'];

            // ---------------- Fruits (fresh) ----------------
            $items[] = ['name' => 'Apple (Local) - Loose', 'category' => 'Fruits', 'price' => 220, 'cost_price' => 160, 'sku' => 'FR-001', 'unit' => 'kg', 'stock_quantity' => 40, 'low_stock_threshold' => 8, 'description' => 'Local apples sold per kg.'];
            $items[] = ['name' => 'Orange - Loose', 'category' => 'Fruits', 'price' => 180, 'cost_price' => 130, 'sku' => 'FR-002', 'unit' => 'kg', 'stock_quantity' => 50, 'low_stock_threshold' => 10, 'description' => 'Fresh oranges sold per kg.'];
            $items[] = ['name' => 'Mango (Seasonal) - Loose', 'category' => 'Fruits', 'price' => 300, 'cost_price' => 200, 'sku' => 'FR-003', 'unit' => 'kg', 'stock_quantity' => 30, 'low_stock_threshold' => 5, 'description' => 'Seasonal mangoes sold per kg.'];
            $items[] = ['name' => 'Grapes - Loose', 'category' => 'Fruits', 'price' => 260, 'cost_price' => 180, 'sku' => 'FR-004', 'unit' => 'kg', 'stock_quantity' => 35, 'low_stock_threshold' => 6, 'description' => 'Fresh grapes sold per kg.'];

            foreach ($items as $itemData) {
                $category = Category::where('restaurant_id', $restaurant->id)
                    ->where('name', $itemData['category'])
                    ->first();

                MenuItem::firstOrCreate([
                    'restaurant_id' => $restaurant->id,
                    'sku' => $itemData['sku'],
                ], [
                    'category_id' => $category?->id,
                    'name' => $itemData['name'],
                    'description' => $itemData['description'],
                    'price' => $itemData['price'],
                    'cost_price' => $itemData['cost_price'],
                    'unit' => $itemData['unit'],
                    'is_available' => true,
                    'track_stock' => true,
                    'stock_quantity' => $itemData['stock_quantity'],
                    'low_stock_threshold' => $itemData['low_stock_threshold'],
                ]);
            }
        });
    }
}
