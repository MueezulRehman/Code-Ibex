<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vital_signs') || Schema::hasColumn('vital_signs', 'patient_id')) {
            return;
        }

        Schema::table('vital_signs', function (Blueprint $table): void {
            $table->foreignId('patient_id')->nullable()->after('restaurant_id')->constrained('patients')->nullOnDelete();
            $table->index(['restaurant_id', 'patient_id'], 'vitals_patient_idx');
        });

        DB::statement(
            'UPDATE vital_signs
             SET patient_id = (
                 SELECT patient_id
                 FROM hospital_admissions
                 WHERE hospital_admissions.id = vital_signs.hospital_admission_id
             )
             WHERE patient_id IS NULL'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('vital_signs') || ! Schema::hasColumn('vital_signs', 'patient_id')) {
            return;
        }

        Schema::table('vital_signs', function (Blueprint $table): void {
            $table->dropForeign(['patient_id']);
            $table->dropIndex('vitals_patient_idx');
            $table->dropColumn('patient_id');
        });
    }
};
