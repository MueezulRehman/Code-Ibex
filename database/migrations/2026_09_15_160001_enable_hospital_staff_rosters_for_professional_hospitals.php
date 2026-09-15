<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->changeModule(true);
    }

    public function down(): void
    {
        $this->changeModule(false);
    }

    private function changeModule(bool $enable): void
    {
        $restaurants = DB::table('restaurants')
            ->join('business_types', 'business_types.id', '=', 'restaurants.business_type_id')
            ->whereRaw('LOWER(business_types.name) = ?', ['professional hospital'])
            ->select('restaurants.id', 'restaurants.enabled_modules')
            ->get();

        foreach ($restaurants as $restaurant) {
            $modules = json_decode($restaurant->enabled_modules ?? '[]', true);
            $modules = is_array($modules) ? $modules : [];
            $modules = $enable
                ? array_values(array_unique(array_merge($modules, ['hospital-staff-rosters'])))
                : array_values(array_diff($modules, ['hospital-staff-rosters']));

            DB::table('restaurants')->where('id', $restaurant->id)->update([
                'enabled_modules' => json_encode($modules),
            ]);
        }
    }
};
