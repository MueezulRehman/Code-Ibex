<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hospital_transfers')) {
            return;
        }

        Schema::create('hospital_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('from_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('to_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('transferred_at');
            $table->string('reason', 500);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'hospital_admission_id', 'transferred_at'], 'hospital_transfers_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_transfers');
    }
};
