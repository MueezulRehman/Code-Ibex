<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('radiology_order_id')->constrained('radiology_orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('hospital_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('result_value');
            $table->string('status', 30)->default('entered');
            $table->dateTime('resulted_at');
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('radiology_order_id');
            $table->index(['restaurant_id', 'hospital_admission_id', 'status'], 'rad_results_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_results');
    }
};
