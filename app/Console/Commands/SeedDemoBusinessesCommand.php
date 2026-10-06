<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class SeedDemoBusinessesCommand extends Command
{
    protected $signature = 'businesses:seed-demos
                            {--dry-run : Show the demo businesses without writing data}
                            {--create-only : Create missing central business rows, but do not provision tenant databases or seed records}
                            {--tenant-database-prefix= : Prefix for pre-created empty tenant databases; business ID is appended}
                            {--confirm : Confirm creation/provisioning/seeding of demo businesses}';

    protected $description = 'Create and seed the curated demo businesses without running the full production database seeder';

    public function handle(DemoBusinessesSeeder $businessSeeder, TenantProvisioner $provisioner): int
    {
        $prefix = trim((string) $this->option('tenant-database-prefix'));
        if ($prefix !== '' && ! preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            $this->error('The tenant database prefix may contain only letters, numbers, and underscores.');

            return self::INVALID;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: no business rows, tenant databases, or demo records will be written.');
            foreach (DemoBusinessesSeeder::definitions() as $definition) {
                $existing = Restaurant::where('slug', $definition['slug'])->first();
                $state = $existing ? "existing business #{$existing->id}" : 'new business';
                $this->line("- {$definition['name']} [{$definition['slug']}] ({$state})");
            }
            $this->line('Would provision tenants and seed Taste Hut, City Pharmacy, Al-Barkat General Store, Karachi Broast, Food Clinic, Al Majid Aryans Store, Amjd Karyana Store, and Professional Hospital demo records.');

            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $this->error('No changes made. Review the dry run, then repeat with --confirm.');

            return self::INVALID;
        }

        if (! $this->option('create-only') && blank(config('app.key'))) {
            $this->error('APP_KEY is missing. Set the existing application key before provisioning encrypted tenant database configuration.');

            return self::FAILURE;
        }

        try {
            $businesses = $businessSeeder->createBusinesses();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Could not prepare the demo business records. Review the Laravel log for the specific error.');

            return self::FAILURE;
        }

        if ($this->option('create-only')) {
            $this->info('Demo business rows are ready. No tenant database or demo catalog data was changed.');
            foreach ($businesses as $business) {
                $database = $business->hasTenantDatabase()
                    ? (string) ($business->getTenantDatabaseConfig()['database'] ?? 'configured tenant database')
                    : ($prefix !== '' ? $prefix.$business->id : 'use the configured tenant database prefix');
                $this->line("Business #{$business->id}: {$business->name} — tenant database: {$database}");
            }
            $this->line('Create/assign empty tenant databases in the hosting panel if required, then repeat without --create-only to provision and seed.');

            return self::SUCCESS;
        }

        $maxBusinessId = max(array_map(static fn (Restaurant $business): int => $business->id, $businesses));
        if ($prefix !== '' && strlen($prefix.$maxBusinessId) > 64) {
            $this->error('The tenant database prefix plus business ID exceeds MySQL’s 64-character database-name limit.');

            return self::INVALID;
        }

        if ($prefix !== '') {
            foreach ($businesses as $business) {
                if ($business->hasTenantDatabase()) {
                    continue;
                }

                $database = $prefix.$business->id;
                $tenantConfig = array_merge(config('tenancy.template', []), ['database' => $database]);
                if (! $this->tenantDatabaseIsEmpty($tenantConfig)) {
                    $this->error("Tenant database [{$database}] is unavailable or not empty. Create a separate empty database for this demo business.");

                    return self::FAILURE;
                }

                $business->forceFill(['db_connection' => $tenantConfig])->save();
                $business->refresh();
            }
        }

        foreach ($businesses as $business) {
            try {
                $provisioner->provision($business, true);
                $this->info("Tenant ready: #{$business->id} {$business->name}");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Provisioning failed for business #{$business->id} ({$business->name}). Fix the reported database issue, then rerun this command; existing demo businesses will be reused.");

                return self::FAILURE;
            }
        }

        try {
            $this->seedBusinessRecords($businesses);
            $createdManagerAccounts = $this->ensureDemoManagerAccounts($businesses);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Tenant schemas are ready, but one or more demo datasets could not be seeded. Review the Laravel log, correct the issue, then safely rerun this command.');

            return self::FAILURE;
        } finally {
            $provisioner->restoreCentralConnection(config('tenancy.central_connection', config('database.default')));
            Tenancy::end();
        }

        $this->info('All curated demo businesses and sample datasets are ready.');
        if ($createdManagerAccounts > 0) {
            $this->line("Created {$createdManagerAccounts} missing demo manager account(s). Assign their one-time passwords from Super Admin → User Access.");
        } else {
            $this->line('Every demo business already had an internal manager account; existing accounts and passwords were left unchanged.');
        }
        $this->line('Open the Super Admin Businesses page and use Enter business to inspect each tenant.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, Restaurant>  $businesses
     */
    private function ensureDemoManagerAccounts(array $businesses): int
    {
        $created = 0;

        foreach ($businesses as $business) {
            if (User::where('restaurant_id', $business->id)
                ->whereIn('role', ['admin', 'manager'])
                ->exists()) {
                continue;
            }

            $sequence = (int) $business->id;
            do {
                $phone = sprintf('030%08d', $sequence++);
            } while (User::where('phone', $phone)->exists());

            $email = sprintf(
                'manager.%s.%d@demo.codeibex.invalid',
                Str::slug($business->slug ?: $business->name),
                $business->id,
            );

            User::create([
                'name' => $business->name . ' Manager',
                'phone' => $phone,
                'email' => $email,
                'role' => 'admin',
                'restaurant_id' => $business->id,
                'password' => Hash::make(Str::random(48)),
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @param  array<int, Restaurant>  $businesses
     */
    private function seedBusinessRecords(array $businesses): void
    {
        $bySlug = collect($businesses)->keyBy('slug');

        app(TasteHutSeeder::class)->run();

        $pharmacy = $bySlug->get('city-pharmacy');
        if (! $pharmacy) {
            throw new \RuntimeException('The City Pharmacy demo business was not created.');
        }
        Tenancy::runFor($pharmacy, function (): void {
            foreach ([
                MedicineCategorySeeder::class,
                MedicineUnitSeeder::class,
                ManufacturerSeeder::class,
                MedicineSeeder::class,
                MedicineBatchSeeder::class,
            ] as $seederClass) {
                app($seederClass)->run();
            }
        });
        app(PakistaniMedicineSeeder::class)->run();
        app(PakistaniGeneralStoreSeeder::class)->run();
        app(AlMajidAryansStoreSeeder::class)->run();
        app(AmjdKaryanaStoreSeeder::class)->run();
        app(FastFoodSeeder::class)->run();
        app(FoodClinicSeeder::class)->run();

        app(ProfessionalHospitalDemoSeeder::class)
            ->setRestaurant($bySlug->get('professional-hospital-demo')->fresh('businessType'))
            ->run();
    }

    private function tenantDatabaseIsEmpty(array $tenantConfig): bool
    {
        $connection = config('tenancy.connection', 'tenant');
        config(["database.connections.{$connection}" => $tenantConfig]);
        DB::purge($connection);

        try {
            return DB::connection($connection)->getSchemaBuilder()->getTableListing() === [];
        } catch (Throwable) {
            return false;
        } finally {
            DB::purge($connection);
        }
    }
}
