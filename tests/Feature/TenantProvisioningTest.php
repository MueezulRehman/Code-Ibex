<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\RestaurantController;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_inspection_requires_migrations_and_matching_restaurant_mirror(): void
    {
        $dbPath = database_path('tenant-inspection-test.sqlite');
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }
        touch($dbPath);

        $fixtureConnection = 'tenant_inspection_fixture';
        config(["database.connections.{$fixtureConnection}" => [
            'driver' => 'sqlite',
            'database' => $dbPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        try {
            Schema::connection($fixtureConnection)->create('migrations', function ($table): void {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });
            Schema::connection($fixtureConnection)->create('restaurants', function ($table): void {
                $table->unsignedBigInteger('id')->primary();
                $table->string('slug');
            });
            Schema::connection($fixtureConnection)->create('users', function ($table): void {
                $table->increments('id');
                $table->string('name');
                $table->string('phone');
                $table->string('email')->nullable();
                $table->string('role');
                $table->string('password');
                $table->boolean('is_active');
                $table->date('joined_at')->nullable();
                $table->timestamps();
            });

            $migrationFiles = glob(database_path('tenant_migrations/*.php')) ?: [];
            foreach ($migrationFiles as $index => $migrationFile) {
                DB::connection($fixtureConnection)->table('migrations')->insert([
                    'migration' => pathinfo($migrationFile, PATHINFO_FILENAME),
                    'batch' => $index + 1,
                ]);
            }

            $restaurant = Restaurant::create([
                'name' => 'Inspection Tenant',
                'slug' => 'inspection-tenant',
                'db_connection' => ['driver' => 'sqlite', 'database' => $dbPath],
            ]);
            DB::connection($fixtureConnection)->table('restaurants')->insert([
                'id' => $restaurant->id,
                'slug' => $restaurant->slug,
            ]);
            DB::purge($fixtureConnection);

            $status = app(TenantProvisioner::class)->inspectDatabase($restaurant);

            $this->assertSame('ready', $status['status']);
            $this->assertTrue($status['ready']);
            $this->assertSame($dbPath, $status['database']);

            $manager = User::factory()->create([
                'name' => 'Mirrored Manager',
                'phone' => '03001110015',
                'role' => 'manager',
                'restaurant_id' => $restaurant->id,
            ]);
            app(TenantProvisioner::class)->syncManagerAccount($restaurant, $manager);

            $tenantManager = DB::connection($fixtureConnection)->table('users')->where('id', $manager->id)->first();
            $this->assertNotNull($tenantManager);
            $this->assertSame('admin', $tenantManager->role);
            $this->assertSame($manager->phone, $tenantManager->phone);
        } finally {
            DB::purge($fixtureConnection);
            DB::purge('tenant_inspect_' . (isset($restaurant) ? $restaurant->id : ''));
            @unlink($dbPath);
        }
    }

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
