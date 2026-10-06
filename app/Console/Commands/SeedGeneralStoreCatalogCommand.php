<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Database\Seeders\PakistaniGeneralStoreSeeder;
use Illuminate\Console\Command;
use Throwable;

class SeedGeneralStoreCatalogCommand extends Command
{
    protected $signature = 'businesses:seed-general-store-catalog
                            {slug : Existing business slug to receive the sample General Store catalog}
                            {--dry-run : Check the business and tenant readiness without writing}
                            {--confirm : Confirm inserting missing catalog categories and items}';

    protected $description = 'Seed the sample General Store catalog into one existing, ready business tenant';

    public function handle(
        TenantProvisioner $provisioner,
        PakistaniGeneralStoreSeeder $seeder,
    ): int {
        $business = Restaurant::where('slug', $this->argument('slug'))->first();
        if (! $business) {
            $this->error("No existing business was found with slug [{$this->argument('slug')}]. No data was changed.");

            return self::FAILURE;
        }

        $health = $provisioner->inspectDatabase($business);
        $this->line("Business: #{$business->id} {$business->name} [{$business->slug}]");
        $this->line('Tenant database: '.($health['database'] ?? 'not configured'));
        $this->line('Tenant status: '.$health['status']);

        if ($this->option('dry-run')) {
            $this->info('Dry run: no catalog categories or items were written.');
            if (! $health['ready']) {
                $this->warn('Configure and fully initialize this business tenant before seeding its catalog.');
            } else {
                $this->line('Would add missing sample General Store categories and items. Existing categories/items are preserved.');
            }

            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $this->error('No changes made. Review the dry run, then repeat with --confirm.');

            return self::INVALID;
        }

        if (! $health['ready']) {
            $this->error('Catalog seeding was blocked because this business tenant is not ready. No catalog data was changed.');

            return self::FAILURE;
        }

        try {
            $seeder->setRestaurant($business)->run();
            $this->info("Sample General Store catalog seeded for {$business->name}. Existing catalog rows were not overwritten.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Catalog seeding failed. Review the Laravel log for details; safely rerun after correcting the issue.');

            return self::FAILURE;
        } finally {
            $provisioner->restoreCentralConnection(config('tenancy.central_connection', config('database.default')));
            Tenancy::end();
        }
    }
}
