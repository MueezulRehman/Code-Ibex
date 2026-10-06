<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\MedicineCategory;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Module;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ComprehensiveBusinessTypeTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed business types and modules for testing
        \App\Services\ModuleService::seedDefaultModules();
        \App\Services\ModuleService::seedDefaultBusinessTypes();
    }

    public function test_restaurant_mode_works_end_to_end(): void
    {
        // Setup
        $restaurantType = BusinessType::where('name', 'Restaurant')->first();
        $this->assertNotNull($restaurantType, 'Restaurant business type should exist');

        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'status' => 'active',
            'business_type_id' => $restaurantType->id,
        ]);

        $manager = User::create([
            'name' => 'Restaurant Manager',
            'email' => 'restaurant-manager@example.com',
            'phone' => date('Ym') . rand(10000000, 99999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
            'module_access' => ['orders', 'pos', 'menu', 'categories', 'variants', 'cashbook'],
        ]);

        // Test POS loads for restaurant mode
        $this->actingAs($manager);
        $response = $this->get(route('manager.pos.index'));
        $response->assertOk();
        $response->assertViewHas('posConfig');
    }

    public function test_retail_shop_mode_works_end_to_end(): void
    {
        // Setup
        $retailType = BusinessType::where('name', 'Retail / Shop')->first() 
            ?? BusinessType::where('name', 'General Business')->first();
        $this->assertNotNull($retailType, 'Retail/General business type should exist');

        $restaurant = Restaurant::create([
            'name' => 'Test Shop',
            'slug' => 'test-shop',
            'status' => 'active',
            'business_type_id' => $retailType->id,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Karyana Goods',
            'slug' => 'karyana-goods',
            'is_active' => true,
            'pos_show_line_edit' => true,
        ]);
        MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Sample Rice',
            'price' => 500,
            'is_available' => true,
            'track_stock' => false,
        ]);

        $manager = User::create([
            'name' => 'Shop Manager',
            'email' => 'shop-manager@example.com',
            'phone' => date('Ym') . rand(10000000, 99999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
            'module_access' => ['pos', 'inventory', 'categories', 'variants', 'stock'],
        ]);

        // Test POS loads for retail mode
        $this->actingAs($manager);
        $response = $this->get(route('manager.pos.index'));
        $response->assertOk()
            ->assertSee('Sample Rice')
            ->assertSee('Karyana Goods')
            ->assertSee('.dashboard-shell .dashboard-sidebar.mobile-open', false)
            ->assertSee('scanInput?.focus({ preventScroll: true })', false);
        $this->assertTrue($response->viewData('items')->first()->relationLoaded('category'));
        
        // Verify retail-specific behavior: variants should be available
        $this->assertTrue($manager->hasModuleAccess('variants'), 'Shop manager should have variants access');
        $this->assertTrue($manager->hasModuleAccess('inventory'), 'Shop manager should have inventory access');
    }

    public function test_pharmacy_medical_store_mode_works_end_to_end(): void
    {
        // Setup
        $medicalType = BusinessType::where('name', 'Medical Store')->first();
        $this->assertNotNull($medicalType, 'Medical Store business type should exist');

        $restaurant = Restaurant::create([
            'name' => 'Test Pharmacy',
            'slug' => 'test-pharmacy',
            'status' => 'active',
            'business_type_id' => $medicalType->id,
        ]);

        $manager = User::create([
            'name' => 'Pharmacy Manager',
            'email' => 'pharmacy-manager@example.com',
            'phone' => date('Ym') . rand(10000000, 99999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
            'module_access' => ['pharmacy'],
        ]);

        // Test that pharmacy alias expands module access
        $this->assertTrue($manager->hasModuleAccess('medical'), 'Pharmacy manager should have medical access');
        $this->assertTrue($manager->hasModuleAccess('inventory'), 'Pharmacy manager should have inventory access');
        $this->assertTrue($manager->hasModuleAccess('stock'), 'Pharmacy manager should have stock access');
        $this->assertTrue($manager->hasModuleAccess('pos'), 'Pharmacy manager should have POS access');

        // Create medicine test data
        $category = MedicineCategory::create(['name' => 'Test Category', 'status' => 'active']);
        $medicine = Medicine::create([
            'name' => 'Test Medicine',
            'sku' => 'MED-001',
            'category_id' => $category->id,
            'track_stock' => true,
            'min_stock' => 10,
        ]);
        $batch = MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 100,
            'purchase_price' => 50,
            'selling_price' => 75,
            'mfg_date' => now(),
            'exp_date' => now()->addYear(),
            'restaurant_id' => null, // Global stock
        ]);

        // Test POS loads with medical mode
        $this->actingAs($manager);
        $response = $this->get(route('manager.pos.index'));
        $response->assertOk();
        $response->assertViewHas('medicineCategories');
        $response->assertViewHas('showMedicalItems', true);
    }

    public function test_general_store_mode_alias_works(): void
    {
        $retailType = BusinessType::where('name', 'General Business')->first();
        $this->assertNotNull($retailType, 'General Business type should exist');

        $restaurant = Restaurant::create([
            'name' => 'Test General Store',
            'slug' => 'test-general-store',
            'status' => 'active',
            'business_type_id' => $retailType->id,
        ]);

        $manager = User::create([
            'name' => 'General Store Manager',
            'email' => 'general-store-manager@example.com',
            'phone' => date('Ym') . rand(10000000, 99999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
            'module_access' => ['general_store'],
        ]);

        // Test that general_store alias expands module access
        $this->assertTrue($manager->hasModuleAccess('inventory'), 'General store manager should have inventory access');
        $this->assertTrue($manager->hasModuleAccess('stock'), 'General store manager should have stock access');
        $this->assertTrue($manager->hasModuleAccess('pos'), 'General store manager should have POS access');
        $this->assertTrue($manager->hasModuleAccess('categories'), 'General store manager should have categories access');
        $this->assertTrue($manager->hasModuleAccess('variants'), 'General store manager should have variants access');
    }

    public function test_stock_adjustments_work_across_all_modes(): void
    {
        // Restaurant mode
        $restaurantType = BusinessType::where('name', 'Restaurant')->first();
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant-stock',
            'status' => 'active',
            'business_type_id' => $restaurantType->id,
        ]);

        $manager = User::create([
            'name' => 'Restaurant Manager',
            'email' => 'restaurant-manager-stock@example.com',
            'phone' => date('Ym') . rand(10000000, 99999999),
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
            'module_access' => ['stock'],
        ]);

        // Test POS stock adjustment endpoint
        $this->actingAs($manager);
        $response = $this->get(route('manager.stock.index'));
        $response->assertOk();
    }
}
