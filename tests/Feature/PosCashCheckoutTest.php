<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Http\Controllers\Admin\PosController;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PosCashCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_checkout_records_cash_tendered_and_change(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Cash Test Restaurant',
            'slug' => 'cash-test-restaurant',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $user = User::factory()->create([
            'name' => 'Cash POS User',
            'phone' => '2222222222',
            'role' => 'admin',
            'restaurant_id' => $restaurant->id,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Drinks',
            'slug' => 'drinks',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Tea',
            'price' => 120,
            'is_available' => true,
            'track_stock' => false,
        ]);

        $this->actingAs($user, 'web');

        $request = Request::create('/manager/pos/checkout', 'POST', [
            'order_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_received' => 300,
            'customer_name' => 'Walk-in Customer',
            'cart' => [[
                'type' => 'menu_item',
                'id' => $menuItem->id,
                'quantity' => 1,
            ]],
        ]);

        $response = app(PosController::class)->checkout($request);

        $this->assertTrue($response->isRedirect());

        $order = Order::where('restaurant_id', $restaurant->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertSame(
            route('manager.pos.receipt', ['order' => $order->id, 'print' => 1]),
            $response->getTargetUrl()
        );
        $this->assertSame(300.0, (float) $order->amount_received);
        $this->assertSame(180.0, (float) $order->change_amount);
        $this->assertSame('cash', $order->payment_method);
    }

    public function test_receipt_page_offers_pdf_download_and_share_while_retaining_print_action(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'PDF Receipt Shop',
            'slug' => 'pdf-receipt-shop',
            'status' => 'active',
            'plan' => 'basic',
        ]);
        $manager = User::factory()->create([
            'phone' => '2222222299',
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
        ]);
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'cashier_id' => $manager->id,
            'order_number' => 'PDF-RECEIPT-1',
            'tracking_token' => (string) \Illuminate\Support\Str::uuid(),
            'order_type' => 'takeaway',
            'channel' => 'pos',
            'status' => 'confirmed',
            'customer_name' => 'Test Customer',
            'customer_phone' => '03001234567',
            'subtotal' => 250,
            'delivery_fee' => 0,
            'total' => 250,
            'payment_method' => 'cash',
        ]);

        $this->actingAs($manager);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureModuleEnabled::class);
        $salesResponse = $this->get(route('manager.sales.index'));
        $salesResponse->assertOk()
            ->assertSee(route('manager.pos.receipt', ['order' => $order->id]), false);

        $response = $this->get(route('manager.pos.receipt', ['order' => $order->id]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('id="share-receipt-pdf"', $html);
        $this->assertStringContainsString('id="download-receipt-pdf"', $html);
        $this->assertStringContainsString('Print / Save as PDF', $html);
        $this->assertStringContainsString('PDF Receipt Shop', $html);
    }

    public function test_pos_checkout_adds_partial_payment_to_customer_balance(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Credit POS Restaurant',
            'slug' => 'credit-pos-restaurant',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $user = User::factory()->create([
            'name' => 'Credit POS User',
            'phone' => '3333333333',
            'role' => 'admin',
            'restaurant_id' => $restaurant->id,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Meals',
            'slug' => 'meals',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Burger',
            'price' => 250,
            'is_available' => true,
            'track_stock' => false,
        ]);

        $customer = Customer::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Ayesha',
            'phone' => '4444444444',
            'password' => bcrypt('secret'),
            'balance' => 0,
        ]);

        $this->actingAs($user, 'web');

        $request = Request::create('/manager/pos/checkout', 'POST', [
            'order_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_received' => 100,
            'customer_name' => 'Ayesha',
            'customer_phone' => $customer->phone,
            'cart' => [[
                'type' => 'menu_item',
                'id' => $menuItem->id,
                'quantity' => 1,
            ]],
        ]);

        $response = app(PosController::class)->checkout($request);

        $this->assertTrue($response->isRedirect());

        $customer->refresh();
        $this->assertSame(150.0, (float) $customer->balance);
        $this->assertSame(1, $customer->balanceTransactions()->count());
    }

    public function test_pos_checkout_deducts_branch_inventory_when_branch_is_selected(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Branch Deduction POS',
            'slug' => 'branch-deduction-pos',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Downtown',
            'code' => 'DT',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Branch POS User',
            'phone' => '6666666666',
            'role' => 'admin',
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Meals',
            'slug' => 'meals',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Sandwich',
            'price' => 180,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 10,
        ]);

        BranchInventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'item_type' => 'menu_item',
            'item_id' => $menuItem->id,
            'quantity' => 10,
        ]);

        $this->actingAs($user, 'web');

        $request = Request::create('/manager/pos/checkout', 'POST', [
            'order_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_received' => 400,
            'customer_name' => 'Walk-in Customer',
            'branch_id' => $branch->id,
            'cart' => [[
                'type' => 'menu_item',
                'id' => $menuItem->id,
                'quantity' => 2,
            ]],
        ]);

        $response = app(PosController::class)->checkout($request);

        $this->assertTrue($response->isRedirect());
        $branchStock = BranchInventory::where('restaurant_id', $restaurant->id)->where('branch_id', $branch->id)->where('item_type', 'menu_item')->where('item_id', $menuItem->id)->first();
        $this->assertNotNull($branchStock);
        $this->assertSame(8.0, (float) $branchStock->quantity);
    }

    public function test_pos_checkout_preserves_cart_when_batch_is_expired(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Expired Batch POS',
            'slug' => 'expired-batch-pos',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $user = User::factory()->create([
            'name' => 'Expired Batch User',
            'phone' => '5555555555',
            'role' => 'admin',
            'restaurant_id' => $restaurant->id,
        ]);

        $medicine = Medicine::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Amox',
            'sku' => 'AMX-001',
            'requires_prescription' => false,
            'track_stock' => false,
        ]);

        $batch = MedicineBatch::create([
            'restaurant_id' => $restaurant->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'EXP-1',
            'expiry_date' => now()->subDay(),
            'selling_price' => 90,
            'quantity' => 5,
        ]);

        $this->actingAs($user, 'web');

        $request = Request::create('/manager/pos/checkout', 'POST', [
            'order_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_received' => 100,
            'customer_name' => 'Walk-in Customer',
            'cart' => [[
                'type' => 'medicine_batch',
                'id' => $batch->id,
                'quantity' => 1,
            ]],
        ]);

        $response = app(PosController::class)->checkout($request);

        $this->assertTrue($response->isRedirect());
        $this->assertSame([[
            'type' => 'medicine_batch',
            'id' => $batch->id,
            'quantity' => 1,
        ]], session('pos_last_cart'));
        $this->assertSame(['type' => 'medicine_batch', 'id' => $batch->id], session('pos_error_highlight'));
    }
}
