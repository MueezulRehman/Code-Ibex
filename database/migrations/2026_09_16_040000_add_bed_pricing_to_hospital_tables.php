<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wards', function (Blueprint $table): void {
            $table->string('room_category', 100)->nullable()->after('description');
            $table->decimal('daily_rate', 12, 2)->default(0)->after('room_category');
        });
        Schema::table('hospital_admissions', function (Blueprint $table): void {
            $table->decimal('bed_daily_rate', 12, 2)->nullable()->after('bed_id');
        });
    }

    public function down(): void
    {
        Schema::table('hospital_admissions', fn (Blueprint $table) => $table->dropColumn('bed_daily_rate'));
        Schema::table('wards', fn (Blueprint $table) => $table->dropColumn(['room_category', 'daily_rate']));
    }
};
