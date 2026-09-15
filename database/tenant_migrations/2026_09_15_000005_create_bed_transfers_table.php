<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bed_transfers')) {
            return;
        }

        Schema::create('bed_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('new_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('previous_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('new_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('previous_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('new_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('transferred_at');
            $table->string('reason', 500);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'hospital_admission_id', 'transferred_at'], 'bed_transfers_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_transfers');
    }
};
