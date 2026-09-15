<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_medication_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'hmo_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients', 'id', 'hmo_patient_fk')->restrictOnDelete();
            $table->foreignId('hospital_admission_id')->constrained('hospital_admissions', 'id', 'hmo_admission_fk')->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines', 'id', 'hmo_medicine_fk')->restrictOnDelete();
            $table->foreignId('ordered_by')->constrained('users', 'id', 'hmo_ordered_by_fk')->restrictOnDelete();
            $table->string('order_number', 60);
            $table->string('dose', 100);
            $table->string('route', 50);
            $table->string('frequency', 100)->nullable();
            $table->dateTime('scheduled_start_at');
            $table->dateTime('scheduled_end_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->dateTime('ordered_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'order_number']);
            $table->index(['restaurant_id', 'hospital_admission_id', 'status'], 'hospital_med_order_scope_idx');
        });

        Schema::create('hospital_medication_administrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'hma_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients', 'id', 'hma_patient_fk')->restrictOnDelete();
            $table->foreignId('hospital_admission_id')->constrained('hospital_admissions', 'id', 'hma_admission_fk')->cascadeOnDelete();
            $table->foreignId('hospital_medication_order_id')->constrained('hospital_medication_orders', 'id', 'hma_order_fk')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users', 'id', 'hma_recorded_by_fk')->restrictOnDelete();
            $table->foreignId('administered_by')->nullable()->constrained('users', 'id', 'hma_administered_by_fk')->nullOnDelete();
            $table->string('dose', 100);
            $table->string('route', 50);
            $table->dateTime('scheduled_at');
            $table->dateTime('administered_at')->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->string('idempotency_key', 100)->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();
            $table->unique(['restaurant_id', 'hospital_medication_order_id', 'scheduled_at'], 'hospital_med_admin_schedule_unique');
            $table->unique(['restaurant_id', 'idempotency_key'], 'hospital_med_admin_idempotency_unique');
            $table->index(['restaurant_id', 'hospital_admission_id', 'status'], 'hospital_med_admin_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_medication_administrations');
        Schema::dropIfExists('hospital_medication_orders');
    }
};
