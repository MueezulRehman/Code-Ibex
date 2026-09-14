<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('hospital_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->string('order_number', 50);
            $table->string('study_name', 255);
            $table->string('priority', 20)->default('routine');
            $table->string('status', 30)->default('ordered');
            $table->dateTime('ordered_at');
            $table->text('clinical_notes')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'order_number']);
            $table->index(['restaurant_id', 'hospital_admission_id', 'status'], 'rad_orders_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_orders');
    }
};
