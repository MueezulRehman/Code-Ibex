<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\TenantProvisioner;
use Database\Seeders\PakistaniGeneralStoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SeedGeneralStoreCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_targets_existing_business_without_writing_catalog(): void
    {
        $business = Restaurant::create([
            'name' => 'Hassan Ashraf Karyana Store',
            'slug' => 'hassankaryanastore',
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('inspectDatabase')
            ->once()
            ->with(Mockery::on(fn (Restaurant $candidate): bool => $candidate->is($business)))
            ->andReturn(['status' => 'ready', 'ready' => true, 'database' => 'tenant_1']);
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $seeder = Mockery::mock(PakistaniGeneralStoreSeeder::class);
        $seeder->shouldNotReceive('setRestaurant');
        $this->app->instance(PakistaniGeneralStoreSeeder::class, $seeder);

        $this->artisan('businesses:seed-general-store-catalog', [
            'slug' => 'hassankaryanastore',
            '--dry-run' => true,
        ])
            ->expectsOutput('Business: #'.$business->id.' Hassan Ashraf Karyana Store [hassankaryanastore]')
            ->expectsOutput('Tenant database: tenant_1')
            ->expectsOutput('Dry run: no catalog categories or items were written.')
            ->expectsOutput('Would add missing sample General Store categories and items. Existing categories/items are preserved.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('restaurants', 1);
    }

    public function test_confirmed_command_seeds_only_the_selected_existing_business(): void
    {
        $business = Restaurant::create([
            'name' => 'Hassan Ashraf Karyana Store',
            'slug' => 'hassankaryanastore',
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('inspectDatabase')
            ->once()
            ->with(Mockery::on(fn (Restaurant $candidate): bool => $candidate->is($business)))
            ->andReturn(['status' => 'ready', 'ready' => true, 'database' => 'tenant_1']);
        $provisioner->shouldReceive('restoreCentralConnection')->once();
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $seeder = Mockery::mock(PakistaniGeneralStoreSeeder::class);
        $seeder->shouldReceive('setRestaurant')
            ->once()
            ->with(Mockery::on(fn (Restaurant $candidate): bool => $candidate->is($business)))
            ->andReturnSelf();
        $seeder->shouldReceive('run')->once();
        $this->app->instance(PakistaniGeneralStoreSeeder::class, $seeder);

        $this->artisan('businesses:seed-general-store-catalog', [
            'slug' => 'hassankaryanastore',
            '--confirm' => true,
        ])
            ->expectsOutput('Sample General Store catalog seeded for Hassan Ashraf Karyana Store. Existing catalog rows were not overwritten.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('restaurants', 1);
    }

    public function test_command_blocks_seeding_when_tenant_is_not_ready(): void
    {
        $business = Restaurant::create([
            'name' => 'Hassan Ashraf Karyana Store',
            'slug' => 'hassankaryanastore',
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('inspectDatabase')
            ->once()
            ->andReturn(['status' => 'not_configured', 'ready' => false, 'database' => null]);
        $provisioner->shouldNotReceive('restoreCentralConnection');
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $seeder = Mockery::mock(PakistaniGeneralStoreSeeder::class);
        $seeder->shouldNotReceive('setRestaurant');
        $this->app->instance(PakistaniGeneralStoreSeeder::class, $seeder);

        $this->artisan('businesses:seed-general-store-catalog', [
            'slug' => 'hassankaryanastore',
            '--confirm' => true,
        ])
            ->expectsOutput('Catalog seeding was blocked because this business tenant is not ready. No catalog data was changed.')
            ->assertExitCode(1);
    }
}
