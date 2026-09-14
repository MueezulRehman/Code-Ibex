<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $restaurants = DB::table('restaurants')->join('business_types', 'business_types.id', '=', 'restaurants.business_type_id')
            ->whereRaw('LOWER(business_types.name) = ?', ['professional hospital'])
            ->select('restaurants.id', 'restaurants.enabled_modules')->get();
        foreach ($restaurants as $restaurant) {
            $modules = json_decode($restaurant->enabled_modules ?? '[]', true);
            if (! is_array($modules)) $modules = [];
            $modules[] = 'hospital-billing';
            DB::table('restaurants')->where('id', $restaurant->id)->update(['enabled_modules' => json_encode(array_values(array_unique($modules)))]);
        }
    }
    public function down(): void
    {
        $restaurants = DB::table('restaurants')->join('business_types', 'business_types.id', '=', 'restaurants.business_type_id')
            ->whereRaw('LOWER(business_types.name) = ?', ['professional hospital'])
            ->select('restaurants.id', 'restaurants.enabled_modules')->get();
        foreach ($restaurants as $restaurant) {
            $modules = json_decode($restaurant->enabled_modules ?? '[]', true);
            if (is_array($modules)) DB::table('restaurants')->where('id', $restaurant->id)->update(['enabled_modules' => json_encode(array_values(array_diff($modules, ['hospital-billing'])))]); 
        }
    }
};
