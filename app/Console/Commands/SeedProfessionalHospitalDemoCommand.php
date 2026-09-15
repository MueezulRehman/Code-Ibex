<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\ModuleService;
use Database\Seeders\ProfessionalHospitalDemoSeeder;
use Illuminate\Console\Command;

class SeedProfessionalHospitalDemoCommand extends Command
{
    protected $signature = 'hospital:seed-demo
                            {--restaurant=18 : Explicit Professional Hospital restaurant ID; defaults to the local demo tenant}
                            {--dry-run : Validate the target and report what would be seeded without writing data}';

    protected $description = 'Seed idempotent demo data for one explicitly selected Professional Hospital tenant';

    public function handle(): int
    {
        ModuleService::ensureDefaults();

        $restaurantId = (int) $this->option('restaurant');
        if ($restaurantId < 1) {
            $this->error('A valid --restaurant ID is required; no arbitrary tenant selection is allowed.');

            return self::INVALID;
        }

        $restaurant = Restaurant::with('businessType')->find($restaurantId);
        if (! $restaurant) {
            $this->error("Restaurant #{$restaurantId} was not found.");

            return self::FAILURE;
        }

        if (strcasecmp((string) $restaurant->businessType?->name, 'Professional Hospital') !== 0) {
            $this->error("Restaurant #{$restaurantId} is not a Professional Hospital; refusing to seed it.");

            return self::FAILURE;
        }

        if (! $restaurant->hasTenantDatabase()) {
            $this->error("Restaurant #{$restaurantId} has no tenant database; refusing to write to shared data.");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$restaurant->name} (#{$restaurant->id}) is a valid Professional Hospital tenant.");
            $this->line('Would seed departments, wards/beds, patients/admissions, nursing, vitals, diagnostics, surgery, medication, clinical records, billing, insurance, and reports.');

            return self::SUCCESS;
        }

        $this->info("Seeding {$restaurant->name} (#{$restaurant->id})…");
        app(ProfessionalHospitalDemoSeeder::class)->setRestaurant($restaurant)->run();
        $this->info('Professional Hospital demo data is ready (safe to run again).');

        return self::SUCCESS;
    }
}
