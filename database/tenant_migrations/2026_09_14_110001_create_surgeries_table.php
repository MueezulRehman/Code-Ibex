<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surgeries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('hospital_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operating_room_id')->constrained()->restrictOnDelete();
            $table->foreignId('surgeon_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('scheduled_by')->constrained('users')->restrictOnDelete();
            $table->string('surgery_number', 50);
            $table->string('procedure_name', 255);
            $table->string('status', 30)->default('scheduled');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'surgery_number']);
            $table->index(['restaurant_id', 'operating_room_id', 'starts_at', 'ends_at'], 'surgeries_room_schedule_idx');
            $table->index(['restaurant_id', 'hospital_admission_id', 'status'], 'surgeries_admission_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surgeries');
    }
};
