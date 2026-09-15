<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_care_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'hcp_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('hospital_admission_id')->constrained('hospital_admissions', 'id', 'hcp_admission_fk')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients', 'id', 'hcp_patient_fk')->restrictOnDelete();
            $table->string('problem_diagnosis', 255);
            $table->text('goal');
            $table->text('interventions');
            $table->string('responsible_role', 30)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', 'id', 'hcp_resp_user_fk')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->dateTime('opened_at');
            $table->dateTime('review_due_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('nursing_progress')->nullable();
            $table->string('nursing_status', 20)->nullable();
            $table->dateTime('nursing_progress_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'hospital_admission_id', 'status'], 'hcp_admission_status_idx');
            $table->index(['restaurant_id', 'review_due_at'], 'hcp_review_due_idx');
        });

        Schema::create('hospital_care_plan_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'hcpe_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('hospital_care_plan_id')->constrained('hospital_care_plans', 'id', 'hcpe_plan_fk')->cascadeOnDelete();
            $table->string('action', 20);
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('changed_by')->constrained('users', 'id', 'hcpe_changed_by_fk')->restrictOnDelete();
            $table->dateTime('changed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'hospital_care_plan_id', 'changed_at'], 'hcpe_plan_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_care_plan_events');
        Schema::dropIfExists('hospital_care_plans');
    }
};
