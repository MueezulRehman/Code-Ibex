<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('hospital_admissions', 'hospital_patient_insurance_id')) {
            Schema::table('hospital_admissions', function (Blueprint $table): void {
                $table->unsignedBigInteger('hospital_patient_insurance_id')->nullable()->after('patient_id');
                $table->index(['restaurant_id', 'hospital_patient_insurance_id'], 'hadm_hpi_idx');
                $table->foreign('hospital_patient_insurance_id', 'hadm_hpi_fk')
                    ->references('id')->on('hospital_patient_insurances')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('hospital_billings', 'hospital_patient_insurance_id')) {
            Schema::table('hospital_billings', function (Blueprint $table): void {
                $table->unsignedBigInteger('hospital_patient_insurance_id')->nullable()->after('hospital_admission_id');
                $table->index(['restaurant_id', 'hospital_patient_insurance_id'], 'hbill_hpi_idx');
                $table->foreign('hospital_patient_insurance_id', 'hbill_hpi_fk')
                    ->references('id')->on('hospital_patient_insurances')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hospital_billings', 'hospital_patient_insurance_id')) {
            Schema::table('hospital_billings', function (Blueprint $table): void {
                $table->dropForeign('hbill_hpi_fk');
                $table->dropIndex('hbill_hpi_idx');
                $table->dropColumn('hospital_patient_insurance_id');
            });
        }

        if (Schema::hasColumn('hospital_admissions', 'hospital_patient_insurance_id')) {
            Schema::table('hospital_admissions', function (Blueprint $table): void {
                $table->dropForeign('hadm_hpi_fk');
                $table->dropIndex('hadm_hpi_idx');
                $table->dropColumn('hospital_patient_insurance_id');
            });
        }
    }
};
