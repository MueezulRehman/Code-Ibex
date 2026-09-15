<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalHospitalDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_command_refuses_an_arbitrary_non_hospital_tenant(): void
    {
        ModuleService::ensureDefaults();
        $restaurant = Restaurant::create([
            'name' => 'Unrelated Demo Store',
            'slug' => 'unrelated-demo-store',
            'status' => 'active',
            'enabled_modules' => ['menu'],
        ]);

        $this->artisan('hospital:seed-demo', [
            '--restaurant' => $restaurant->id,
            '--dry-run' => true,
        ])->expectsOutput("Restaurant #{$restaurant->id} is not a Professional Hospital; refusing to seed it.")
            ->assertExitCode(1);
    }

    public function test_demo_command_requires_an_explicit_valid_restaurant_id(): void
    {
        $this->artisan('hospital:seed-demo', [
            '--restaurant' => 0,
            '--dry-run' => true,
        ])->expectsOutput('A valid --restaurant ID is required; no arbitrary tenant selection is allowed.')
            ->assertExitCode(2);
    }
}
