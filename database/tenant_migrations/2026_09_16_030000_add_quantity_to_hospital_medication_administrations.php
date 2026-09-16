<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospital_medication_administrations', function (Blueprint $table): void {
            $table->decimal('administered_quantity', 14, 3)->default(1)->after('administered_at');
        });
    }

    public function down(): void
    {
        Schema::table('hospital_medication_administrations', function (Blueprint $table): void {
            $table->dropColumn('administered_quantity');
        });
    }
};
