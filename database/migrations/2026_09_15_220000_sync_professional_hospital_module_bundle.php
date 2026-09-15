<?php

use App\Services\ModuleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('business_types')) {
            return;
        }

        // Keep this migration safe for installs that have not yet run the
        // application bootstrap default seeder.
        foreach ([
            ['name' => 'Hospital Clinical Notes', 'key' => 'hospital-clinical-notes', 'description' => 'Admission-linked clinical notes and audit history', 'sort_order' => 27, 'is_active' => true],
            ['name' => 'Hospital Emergency Department', 'key' => 'hospital-emergency', 'description' => 'Planned emergency department workflow (not enabled)', 'sort_order' => 90, 'is_active' => false],
            ['name' => 'Hospital Blood Bank', 'key' => 'hospital-blood-bank', 'description' => 'Planned blood bank workflow (not enabled)', 'sort_order' => 91, 'is_active' => false],
            ['name' => 'Hospital Dialysis', 'key' => 'hospital-dialysis', 'description' => 'Planned dialysis workflow (not enabled)', 'sort_order' => 92, 'is_active' => false],
        ] as $module) {
            DB::table('modules')->updateOrInsert(
                ['key' => $module['key']],
                array_merge($module, ['updated_at' => now(), 'created_at' => now()])
            );
        }

        $type = DB::table('business_types')->where('name', 'Professional Hospital')->first();
        if (! $type) {
            return;
        }

        $moduleIds = DB::table('modules')
            ->whereIn('key', ModuleService::professionalHospitalModuleKeys())
            ->where('is_active', true)
            ->pluck('id');

        foreach ($moduleIds as $moduleId) {
            DB::table('business_type_modules')->insertOrIgnore([
                'business_type_id' => $type->id,
                'module_id' => $moduleId,
            ]);
        }

        // Tenant 18 is the documented local demo tenant. Merge the bundle so
        // existing Super Admin customizations are preserved while missing
        // implemented hospital access is repaired.
        if (Schema::hasTable('restaurants')) {
            $restaurant = DB::table('restaurants')->where('id', 18)->first();
            if ($restaurant) {
                $stored = json_decode((string) ($restaurant->enabled_modules ?? '[]'), true);
                $stored = is_array($stored) ? $stored : [];
                $merged = array_values(array_unique(array_merge($stored, ModuleService::professionalHospitalModuleKeys())));
                DB::table('restaurants')->where('id', 18)->update([
                    'enabled_modules' => json_encode($merged),
                    'business_type_id' => $type->id,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Access changes are intentionally not reverted: removing a module
        // from an existing tenant during rollback could revoke live access.
    }
};
