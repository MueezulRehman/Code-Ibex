<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('insurance_claims', 'hospital_patient_insurance_id')) {
            Schema::table('insurance_claims', function (Blueprint $table): void {
                $table->unsignedBigInteger('hospital_patient_insurance_id')->nullable()->after('customer_id');
                $table->index(['restaurant_id', 'hospital_patient_insurance_id'], 'iclaim_hpi_idx');
                $table->foreign('hospital_patient_insurance_id', 'iclaim_hpi_fk')
                    ->references('id')->on('hospital_patient_insurances')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('insurance_claims', 'hospital_patient_insurance_id')) {
            return;
        }

        Schema::table('insurance_claims', function (Blueprint $table): void {
            $table->dropForeign('iclaim_hpi_fk');
            $table->dropIndex('iclaim_hpi_idx');
            $table->dropColumn('hospital_patient_insurance_id');
        });
    }
};
