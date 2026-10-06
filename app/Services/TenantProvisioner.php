<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Codeibex Tenant Database Provisioner (Hardened)
 *
 * - Creates one MySQL/SQLite database per business
 * - Runs the FULL tenant schema (database/tenant_migrations)
 * - Optionally seeds demo data
 * - Safely switches connections and always restores central default
 *
 * Module access stays on the central restaurants.enabled_modules column.
 *
 * @author Mueez Ul Rehman
 */
class TenantProvisioner
{
    /**
     * Mirror a central manager into the tenant users table for tenant-local
     * foreign keys. Tenant schemas represent internal users with the legacy
     * admin role even when the central account uses the manager role.
     */
    public function syncManagerAccount(Restaurant $restaurant, User $manager): void
    {
        $tenantConfig = $restaurant->getTenantDatabaseConfig();
        $connection = 'tenant_manager_sync_' . $restaurant->id;
        config(["database.connections.{$connection}" => $tenantConfig]);

        try {
            $db = DB::connection($connection);
            $db->getPdo();
            $schema = Schema::connection($connection);

            if (! $schema->hasTable('users')) {
                throw new \RuntimeException("Tenant users table is missing for business #{$restaurant->id}.");
            }

            $now = now();
            $record = [
                'name' => $manager->name,
                'phone' => $manager->phone,
                'email' => $manager->email,
                'role' => 'admin',
                'password' => $manager->password,
                'is_active' => $manager->is_active ?? true,
                'updated_at' => $now,
                'created_at' => $manager->created_at ?? $now,
            ];

            if ($schema->hasColumn('users', 'joined_at')) {
                $record['joined_at'] = $manager->joined_at?->format('Y-m-d');
            }

            $db->table('users')->updateOrInsert(['id' => $manager->id], $record);
        } finally {
            DB::purge($connection);
            config(["database.connections.{$connection}" => null]);
        }
    }

    /**
     * Inspect tenant connectivity and migration state without changing the
     * application's active tenant connection or default database.
     *
     * @return array{status:string,ready:bool,database:?string}
     */
    public function inspectDatabase(Restaurant $restaurant): array
    {
        if (! $restaurant->hasTenantDatabase()) {
            return ['status' => 'not_configured', 'ready' => false, 'database' => null];
        }

        $tenantConfig = $restaurant->getTenantDatabaseConfig();
        $database = $tenantConfig['database'] ?? null;
        $connection = 'tenant_inspect_' . $restaurant->id;

        if (! is_string($database) || trim($database) === '') {
            return ['status' => 'not_configured', 'ready' => false, 'database' => null];
        }

        config(["database.connections.{$connection}" => $tenantConfig]);

        try {
            $db = DB::connection($connection);
            $db->getPdo();

            $schema = Schema::connection($connection);
            foreach (['migrations', 'restaurants', 'users'] as $table) {
                if (! $schema->hasTable($table)) {
                    return ['status' => 'schema_incomplete', 'ready' => false, 'database' => $database];
                }
            }

            $applied = array_fill_keys($db->table('migrations')->pluck('migration')->all(), true);
            $migrationFiles = glob(base_path(config('tenancy.migrations_path', 'database/tenant_migrations') . '/*.php')) ?: [];
            foreach ($migrationFiles as $migrationFile) {
                if (! isset($applied[pathinfo($migrationFile, PATHINFO_FILENAME)])) {
                    return ['status' => 'migrations_pending', 'ready' => false, 'database' => $database];
                }
            }

            $tenantMirror = $db->table('restaurants')
                ->where('id', $restaurant->id)
                ->where('slug', $restaurant->slug)
                ->exists();

            if (! $tenantMirror) {
                return ['status' => 'not_initialized', 'ready' => false, 'database' => $database];
            }

            return ['status' => 'ready', 'ready' => true, 'database' => $database];
        } catch (Throwable $exception) {
            Log::warning('Tenant database inspection failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $exception->getMessage(),
            ]);

            return ['status' => 'unreachable', 'ready' => false, 'database' => $database];
        } finally {
            DB::purge($connection);
            config(["database.connections.{$connection}" => null]);
        }
    }

    /**
     * Ensure the restaurant has a dedicated database with full schema.
     * Safe to call multiple times (idempotent migrate).
     */
    public function provision(Restaurant $restaurant, bool $seed = true): bool
    {
        $originalDefault = config('database.default');

        try {
            if (! $restaurant->hasTenantDatabase()) {
                $this->createDatabaseAndAttachConfig($restaurant);
                $restaurant->refresh();
            }

            $this->configureConnection($restaurant);

            // Temporarily make tenant the default so Eloquent / seeders hit the right DB
            config(['database.default' => config('tenancy.connection', 'tenant')]);

            $this->runMigrations();

            if ($seed && config('tenancy.seed_after_migrate', true)) {
                $this->seed($restaurant);
            }

            Log::info('Codeibex tenant provisioned', [
                'restaurant_id' => $restaurant->id,
                'database' => $restaurant->getTenantDatabaseConfig()['database'] ?? null,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Codeibex tenant provision failed', [
                'restaurant_id' => $restaurant->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            // Always restore central connection
            $this->restoreCentralConnection($originalDefault);
        }
    }

    /**
     * Create database and store encrypted connection config on the restaurant.
     */
    public function createDatabaseAndAttachConfig(Restaurant $restaurant): void
    {
        $dbName = $this->databaseNameFor($restaurant);
        $template = config('tenancy.template', []);
        $driver = $template['driver'] ?? 'mysql';

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->createMysqlDatabase($dbName, $template);
        } elseif ($driver === 'sqlite') {
            $this->createSqliteDatabase($dbName);
        } else {
            throw new \RuntimeException("Unsupported tenant driver [{$driver}] for Codeibex.");
        }

        $config = array_merge($template, [
            'database' => $dbName,
        ]);

        if ($driver === 'sqlite') {
            $config['database'] = database_path('tenants/' . $dbName . '.sqlite');
        }

        $restaurant->forceFill([
            'db_connection' => $config,
        ])->save();

        Log::info('Codeibex tenant database created', [
            'restaurant_id' => $restaurant->id,
            'database' => $config['database'],
            'driver' => $driver,
        ]);
    }

    /**
     * Drop a tenant database (use with extreme care).
     */
    public function dropDatabase(Restaurant $restaurant): void
    {
        if (! $restaurant->hasTenantDatabase()) {
            return;
        }

        $config = $restaurant->getTenantDatabaseConfig();
        $driver = $config['driver'] ?? 'mysql';
        $dbName = $config['database'] ?? null;

        if (! $dbName) {
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $safe = str_replace('`', '``', basename($dbName)); // safety
            $pdo = DB::connection(config('tenancy.central_connection', env('DB_CONNECTION', 'mysql')))->getPdo();
            $pdo->exec("DROP DATABASE IF EXISTS `{$safe}`");
        } elseif ($driver === 'sqlite' && is_file($dbName)) {
            @unlink($dbName);
        }

        $restaurant->forceFill(['db_connection' => null])->save();

        Log::warning('Codeibex tenant database dropped', [
            'restaurant_id' => $restaurant->id,
            'database' => $dbName,
        ]);
    }

    protected function createMysqlDatabase(string $dbName, array $template): void
    {
        $charset = $template['charset'] ?? 'utf8mb4';
        $collation = $template['collation'] ?? 'utf8mb4_unicode_ci';

        $central = config('tenancy.central_connection', env('DB_CONNECTION', 'mysql'));
        $pdo = DB::connection($central)->getPdo();
        $safe = str_replace('`', '``', $dbName);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safe}` CHARACTER SET {$charset} COLLATE {$collation}");
    }

    protected function createSqliteDatabase(string $dbName): void
    {
        $dir = database_path('tenants');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $dbName . '.sqlite';
        if (! file_exists($path)) {
            touch($path);
        }
    }

    public function databaseNameFor(Restaurant $restaurant): string
    {
        $prefix = config('tenancy.database_prefix', 'codeibex_tenant_');

        return $prefix . $restaurant->id;
    }

    /**
     * Register the tenant connection in the container and reconnect.
     */
    public function configureConnection(Restaurant $restaurant): void
    {
        $name = config('tenancy.connection', 'tenant');
        $config = $restaurant->getTenantDatabaseConfig();

        if (empty($config['database'])) {
            throw new \RuntimeException("Restaurant #{$restaurant->id} has no tenant database configured.");
        }

        config(["database.connections.{$name}" => $config]);
        DB::purge($name);
        DB::reconnect($name);
    }

    public function runMigrations(): void
    {
        $name = config('tenancy.connection', 'tenant');
        $relative = config('tenancy.migrations_path', 'database/tenant_migrations');
        $path = base_path($relative);

        if (! is_dir($path)) {
            throw new \RuntimeException("Tenant migrations path not found: {$path}");
        }

        Artisan::call('migrate', [
            '--database' => $name,
            '--path' => $relative,
            '--force' => true,
        ]);
    }

    public function seed(Restaurant $restaurant): void
    {
        Tenancy::runFor($restaurant, function () use ($restaurant): void {
            $seeder = app(TenantDatabaseSeeder::class);

            if (method_exists($seeder, 'setRestaurant')) {
                $seeder->setRestaurant($restaurant);
            }

            $seeder->run();
        });
    }

    /**
     * Switch the default connection to this tenant for the rest of the request.
     */
    public function useAsDefault(Restaurant $restaurant): void
    {
        $this->configureConnection($restaurant);
        config(['database.default' => config('tenancy.connection', 'tenant')]);
    }

    /**
     * Restore the central (or previously remembered) connection as default
     * and purge the tenant connection to free the PDO handle.
     */
    public function restoreCentralConnection(?string $originalDefault = null): void
    {
        $tenantConnection = config('tenancy.connection', 'tenant');
        $restoreTo = $originalDefault
            ?? config('tenancy.central_connection', env('DB_CONNECTION', 'mysql'));

        try {
            DB::purge($tenantConnection);
        } catch (Throwable $e) {
            // ignore
        }

        config(['database.default' => $restoreTo]);
    }

    /**
     * Check whether the physical database currently exists.
     */
    public function databaseExists(Restaurant $restaurant): bool
    {
        if (! $restaurant->hasTenantDatabase()) {
            return false;
        }

        $config = $restaurant->getTenantDatabaseConfig();
        $driver = $config['driver'] ?? 'mysql';
        $dbName = $config['database'] ?? null;

        if (! $dbName) {
            return false;
        }

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $central = config('tenancy.central_connection', env('DB_CONNECTION', 'mysql'));
                $result = DB::connection($central)->select(
                    'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                    [$dbName]
                );

                return count($result) > 0;
            }

            if ($driver === 'sqlite') {
                return is_file($dbName);
            }
        } catch (Throwable $e) {
            Log::warning('Codeibex: could not check tenant database existence', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
