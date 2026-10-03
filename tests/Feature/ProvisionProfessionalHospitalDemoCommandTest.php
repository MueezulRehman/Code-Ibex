<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Services\ModuleService;
use App\Services\TenantProvisioner;
use Database\Seeders\ProfessionalHospitalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProvisionProfessionalHospitalDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_create_a_business(): void
    {
        $this->artisan('hospital:provision-demo', [
            '--name' => 'Demo Hospital Dry Run',
            '--slug' => 'demo-hospital-dry-run',
            '--dry-run' => true,
        ])->expectsOutput('Dry run: would create [Demo Hospital Dry Run] with slug [demo-hospital-dry-run] as a Professional Hospital.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('restaurants', ['slug' => 'demo-hospital-dry-run']);
    }

    public function test_confirmed_command_creates_hospital_business_and_provisions_its_tenant(): void
    {
        ModuleService::ensureDefaults();
        $businessType = BusinessType::where('name', 'Professional Hospital')->firstOrFail();
        $plan = SubscriptionPlan::create([
            'name' => 'Demo Plan',
            'slug' => 'demo-plan',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'trial_days' => 14,
            'max_staff' => 50,
            'max_menu_items' => 500,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('provision')
            ->once()
            ->with(Mockery::on(fn (Restaurant $restaurant): bool => $restaurant->slug === 'demo-hospital-live'
                && $restaurant->business_type_id === $businessType->id
                && ! $restaurant->hasTenantDatabase()), false)
            ->andReturn(true);
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $seeder = Mockery::mock(ProfessionalHospitalDemoSeeder::class);
        $seeder->shouldReceive('setRestaurant')->once()->andReturnSelf();
        $seeder->shouldReceive('run')->once();
        $this->app->instance(ProfessionalHospitalDemoSeeder::class, $seeder);

        $this->artisan('hospital:provision-demo', [
            '--name' => 'Demo Hospital Live',
            '--slug' => 'demo-hospital-live',
            '--confirm' => true,
        ])->expectsOutput('Professional Hospital demo business created and seeded successfully.')
            ->assertExitCode(0);

        $restaurant = Restaurant::where('slug', 'demo-hospital-live')->firstOrFail();
        $this->assertSame('active', $restaurant->status);
        $this->assertSame($plan->slug, $restaurant->plan);
        $this->assertFalse($restaurant->hasTenantDatabase());
        $this->assertNotNull($restaurant->subscription);
    }

    public function test_command_requires_explicit_confirmation_before_writing(): void
    {
        $this->artisan('hospital:provision-demo', [
            '--name' => 'Unconfirmed Hospital',
            '--slug' => 'unconfirmed-hospital',
        ])->expectsOutput('No changes made. Review the dry run, then repeat with --confirm to create and seed the demo business.')
            ->assertExitCode(2);

        $this->assertDatabaseMissing('restaurants', ['slug' => 'unconfirmed-hospital']);
    }
}
