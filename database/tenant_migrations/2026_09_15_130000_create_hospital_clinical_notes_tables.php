<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_clinical_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'hcn_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('hospital_admission_id')->constrained('hospital_admissions', 'id', 'hcn_admission_fk')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users', 'id', 'hcn_author_fk')->restrictOnDelete();
            $table->string('note_type', 20);
            $table->text('content');
            $table->dateTime('recorded_at');
            $table->string('status', 20)->default('active');
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users', 'id', 'hcn_voided_by_fk')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'hospital_admission_id', 'recorded_at'], 'hcn_admission_time_idx');
            $table->index(['restaurant_id', 'note_type', 'status'], 'hcn_type_status_idx');
        });

        Schema::create('hospital_clinical_note_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'hcne_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('hospital_clinical_note_id')->constrained('hospital_clinical_notes', 'id', 'hcne_note_fk')->cascadeOnDelete();
            $table->string('action', 20);
            $table->text('previous_content')->nullable();
            $table->text('new_content')->nullable();
            $table->dateTime('previous_recorded_at')->nullable();
            $table->dateTime('new_recorded_at')->nullable();
            $table->foreignId('changed_by')->constrained('users', 'id', 'hcne_changed_by_fk')->restrictOnDelete();
            $table->dateTime('changed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'hospital_clinical_note_id', 'changed_at'], 'hcne_note_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_clinical_note_events');
        Schema::dropIfExists('hospital_clinical_notes');
    }
};
