<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('hospital_patient_insurances')) {
            return;
        }

        Schema::create('hospital_patient_insurances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('payer', 150);
            $table->string('policy_number', 100)->nullable();
            $table->string('member_number', 100)->nullable();
            $table->date('coverage_starts')->nullable();
            $table->date('coverage_ends')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'patient_id'], 'hpi_rest_patient_idx');
            $table->index(['restaurant_id', 'status'], 'hpi_rest_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_patient_insurances');
    }
};
