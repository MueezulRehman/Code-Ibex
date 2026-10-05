<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\ModuleService;
use App\Services\TenantProvisioner;
use Database\Seeders\AlMajidAryansStoreSeeder;
use Database\Seeders\AmjdKaryanaStoreSeeder;
use Database\Seeders\DemoBusinessesSeeder;
use Database\Seeders\FastFoodSeeder;
use Database\Seeders\FoodClinicSeeder;
use Database\Seeders\ManufacturerSeeder;
use Database\Seeders\MedicineBatchSeeder;
use Database\Seeders\MedicineCategorySeeder;
use Database\Seeders\MedicineSeeder;
use Database\Seeders\MedicineUnitSeeder;
use Database\Seeders\PakistaniGeneralStoreSeeder;
use Database\Seeders\PakistaniMedicineSeeder;
use Database\Seeders\ProfessionalHospitalDemoSeeder;
use Database\Seeders\TasteHutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SeedDemoBusinessesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_demo_businesses_without_writing(): void
    {
        $this->artisan('businesses:seed-demos', ['--dry-run' => true])
            ->expectsOutput('Dry run: no business rows, tenant databases, or demo records will be written.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('restaurants', 0);
    }

    public function test_create_only_adds_expected_businesses_without_provisioning_tenants(): void
    {
        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldNotReceive('provision');
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $this->artisan('businesses:seed-demos', [
            '--create-only' => true,
            '--confirm' => true,
        ])->expectsOutput('Demo business rows are ready. No tenant database or demo catalog data was changed.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('restaurants', count(DemoBusinessesSeeder::definitions()));
        $this->assertDatabaseHas('restaurants', [
            'slug' => 'tastehut',
            'status' => 'active',
            'show_on_homepage' => false,
        ]);
        $this->assertDatabaseHas('restaurants', [
            'slug' => 'professional-hospital-demo',
            'status' => 'active',
        ]);
    }

    public function test_confirmed_command_provisions_and_runs_curated_dataset_seeders(): void
    {
        ModuleService::ensureDefaults();

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('provision')->times(count(DemoBusinessesSeeder::definitions()))->andReturn(true);
        $provisioner->shouldReceive('restoreCentralConnection')->once();
        $this->app->instance(TenantProvisioner::class, $provisioner);

        foreach ([
            TasteHutSeeder::class,
            MedicineCategorySeeder::class,
            MedicineUnitSeeder::class,
            ManufacturerSeeder::class,
            MedicineSeeder::class,
            MedicineBatchSeeder::class,
            PakistaniMedicineSeeder::class,
            PakistaniGeneralStoreSeeder::class,
            AlMajidAryansStoreSeeder::class,
            AmjdKaryanaStoreSeeder::class,
            FastFoodSeeder::class,
            FoodClinicSeeder::class,
        ] as $seederClass) {
            $seeder = Mockery::mock($seederClass);
            $seeder->shouldReceive('run')->once();
            $this->app->instance($seederClass, $seeder);
        }

        $hospitalSeeder = Mockery::mock(ProfessionalHospitalDemoSeeder::class);
        $hospitalSeeder->shouldReceive('setRestaurant')
            ->once()
            ->with(Mockery::on(fn (Restaurant $restaurant): bool => $restaurant->slug === 'professional-hospital-demo'))
            ->andReturnSelf();
        $hospitalSeeder->shouldReceive('run')->once();
        $this->app->instance(ProfessionalHospitalDemoSeeder::class, $hospitalSeeder);

        $this->artisan('businesses:seed-demos', ['--confirm' => true])
            ->expectsOutput('All curated demo businesses and sample datasets are ready.')
            ->assertExitCode(0);
    }

    public function test_command_requires_explicit_confirmation(): void
    {
        $this->artisan('businesses:seed-demos')
            ->expectsOutput('No changes made. Review the dry run, then repeat with --confirm.')
            ->assertExitCode(2);

        $this->assertDatabaseCount('restaurants', 0);
    }
}
