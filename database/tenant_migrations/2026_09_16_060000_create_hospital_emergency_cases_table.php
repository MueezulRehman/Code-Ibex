<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_emergency_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hospital_admission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('case_number', 50);
            $table->string('priority', 20)->default('routine');
            $table->string('status', 30)->default('waiting');
            $table->string('destination', 20)->nullable();
            $table->dateTime('arrived_at');
            $table->dateTime('triaged_at')->nullable();
            $table->dateTime('routed_at')->nullable();
            $table->string('chief_complaint', 1000);
            $table->text('triage_notes')->nullable();
            $table->decimal('emergency_charge_amount', 12, 2)->default(0);
            $table->text('routing_notes')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'case_number']);
            $table->index(['restaurant_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_emergency_cases');
    }
};
