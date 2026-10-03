<?php

use App\Services\ModuleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('business_types') || ! Schema::hasTable('restaurants')) {
            return;
        }

        $activeKeys = [
            'hospital-emergency',
            'hospital-icu',
            'hospital-opd',
            'hospital-pharmacy-deepening',
            'hospital-staff-payroll',
        ];

        DB::table('modules')->whereIn('key', $activeKeys)->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);

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

        DB::table('restaurants')
            ->where('business_type_id', $type->id)
            ->orderBy('id')
            ->get(['id', 'enabled_modules'])
            ->each(function (object $restaurant) use ($activeKeys): void {
                $stored = json_decode((string) $restaurant->enabled_modules, true);
                $stored = is_array($stored) ? $stored : [];
                $merged = array_values(array_unique(array_merge($stored, $activeKeys)));

                DB::table('restaurants')->where('id', $restaurant->id)->update([
                    'enabled_modules' => json_encode($merged),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Access is intentionally not revoked during rollback.
    }
};
