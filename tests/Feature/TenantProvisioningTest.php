<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\RestaurantController;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DB::purge('tenant');
        $dbPath = database_path('tenant-provision-test.sqlite');
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }

        parent::tearDown();
    }

    public function test_provisioning_creates_tenant_schema_and_seed_data_for_selected_modules(): void
    {
        DB::purge('tenant');
        $dbPath = database_path('tenant-provision-test.sqlite');
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }

        $restaurant = Restaurant::create([
            'name' => 'Tenant Provision Test',
            'slug' => 'tenant-provision-test',
            'status' => 'active',
            'enabled_modules' => ['menu', 'orders', 'tables'],
            'db_connection' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
            ],
        ]);

        $controller = new RestaurantController();
        $method = new \ReflectionMethod($controller, 'provisionTenantDatabase');
        $method->setAccessible(true);
        $method->invoke($controller, $restaurant);

        $this->assertTrue(Schema::connection('tenant')->hasTable('categories'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('menu_items'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('tables'));
        $this->assertGreaterThan(0, DB::connection('tenant')->table('categories')->count());
        $this->assertGreaterThan(0, DB::connection('tenant')->table('menu_items')->count());
    }
}
