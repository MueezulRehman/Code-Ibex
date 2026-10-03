<?php

namespace App\Console\Commands;

use App\Models\BusinessType;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Services\ModuleService;
use App\Services\TenantProvisioner;
use Database\Seeders\ProfessionalHospitalDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ProvisionProfessionalHospitalDemoCommand extends Command
{
    protected $signature = 'hospital:provision-demo
                            {--name=Professional Hospital Demo : Name for the new demo business}
                            {--slug=professional-hospital-demo : Unique slug for the new demo business}
                            {--database= : Existing tenant database name; omit to let the app create it}
                            {--dry-run : Validate the request without writing data}
                            {--confirm : Confirm creation and seeding of a new demo business}';

    protected $description = 'Create a dedicated Professional Hospital demo business, tenant database, and sample records';

    public function handle(TenantProvisioner $provisioner): int
    {
        $name = trim((string) $this->option('name'));
        $slug = Str::slug((string) $this->option('slug'));
        $tenantDatabase = trim((string) $this->option('database'));

        if ($name === '' || mb_strlen($name) > 255 || $slug === '' || mb_strlen($slug) > 100) {
            $this->error('Provide a valid business name and slug.');

            return self::INVALID;
        }

        if ($tenantDatabase !== '' && ! preg_match('/^[A-Za-z0-9_]+$/', $tenantDatabase)) {
            $this->error('The tenant database name may contain only letters, numbers, and underscores.');

            return self::INVALID;
        }

        if (! Schema::hasTable('restaurants') || ! Schema::hasTable('business_types')) {
            $this->error('Central platform tables are not installed; apply central migrations before provisioning a demo business.');

            return self::FAILURE;
        }

        if (Restaurant::where('slug', $slug)->exists()) {
            $this->error("A business with slug [{$slug}] already exists; refusing to create a duplicate.");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: would create [{$name}] with slug [{$slug}] as a Professional Hospital.");
            $this->line($tenantDatabase !== ''
                ? "Would use the existing tenant database [{$tenantDatabase}]."
                : 'Would create a dedicated tenant database using the configured tenant database prefix.');
            $this->line('Would apply tenant migrations and add demo departments, staff, wards/beds, patients/admissions, clinical records, diagnostics, medication, billing, and reports.');

            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $this->error('No changes made. Review the dry run, then repeat with --confirm to create and seed the demo business.');

            return self::INVALID;
        }

        if (blank(config('app.key'))) {
            $this->error('APP_KEY is missing. Set the application key before storing encrypted tenant database configuration.');

            return self::FAILURE;
        }

        ModuleService::ensureDefaults();

        $businessType = BusinessType::where('name', 'Professional Hospital')
            ->where('is_active', true)
            ->first();

        if (! $businessType) {
            $this->error('The active Professional Hospital business type is unavailable; no business was created.');

            return self::FAILURE;
        }

        $plan = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->first();
        $tenantConfig = null;
        if ($tenantDatabase !== '') {
            $tenantConfig = array_merge(config('tenancy.template', []), [
                'database' => $tenantDatabase,
            ]);

            if (! $this->tenantDatabaseIsEmpty($tenantConfig)) {
                $this->error('The supplied tenant database is unavailable or not empty. Use a newly created, empty database dedicated to this demo.');

                return self::FAILURE;
            }
        }

        $restaurant = Restaurant::create([
            'name' => $name,
            'slug' => $slug,
            'business_type_id' => $businessType->id,
            'plan' => $plan?->slug ?? 'basic',
            'status' => 'active',
            'activated_at' => now(),
            'enabled_modules' => ModuleService::professionalHospitalModuleKeys(),
            'show_on_homepage' => false,
            'storefront_enabled' => false,
            'storefront_module_override' => false,
            'customer_template' => 'default',
            'db_connection' => $tenantConfig,
        ]);

        if ($plan) {
            RestaurantSubscription::create([
                'restaurant_id' => $restaurant->id,
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'auto_renew' => false,
                'current_period_start' => now(),
            ]);
        }

        try {
            $provisioner->provision($restaurant, false);
        } catch (Throwable $exception) {
            report($exception);
            $this->error("Business #{$restaurant->id} was created, but tenant provisioning failed. Check hosting database permissions and do not create the business again.");

            return self::FAILURE;
        }

        try {
            app(ProfessionalHospitalDemoSeeder::class)
                ->setRestaurant($restaurant->fresh('businessType'))
                ->run();
        } catch (Throwable $exception) {
            report($exception);
            $this->error("Business #{$restaurant->id} and its tenant schema exist, but demo records could not be seeded.");
            $this->line("After correcting the reported issue, retry only the data step with: php artisan hospital:seed-demo --restaurant={$restaurant->id}");

            return self::FAILURE;
        }

        $this->info("Professional Hospital demo business created and seeded successfully.");
        $this->line("Business ID: {$restaurant->id}");
        $this->line("Business name: {$restaurant->name}");
        $this->line("Slug: {$restaurant->slug}");
        $this->line('Access it from the Super Admin Businesses page using Enter business.');

        return self::SUCCESS;
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
