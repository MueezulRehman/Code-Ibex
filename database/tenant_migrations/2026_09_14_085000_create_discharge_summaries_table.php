<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discharge_summaries')) {
            return;
        }

        Schema::create('discharge_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_admission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->text('discharge_diagnosis');
            $table->text('discharge_medications')->nullable();
            $table->text('follow_up_instructions')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('discharged_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('discharged_at');
            $table->timestamps();
            $table->index(['restaurant_id', 'discharged_at'], 'discharge_summaries_tenant_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discharge_summaries');
    }
};
