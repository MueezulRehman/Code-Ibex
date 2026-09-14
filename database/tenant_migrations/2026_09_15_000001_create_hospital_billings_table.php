<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hospital_billings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('hospital_admission_id')->constrained()->restrictOnDelete();
            $table->string('charge_type', 30);
            $table->string('description', 1000);
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 20)->default('draft');
            $table->dateTime('charged_at');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
            $table->index(['restaurant_id', 'hospital_admission_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('hospital_billings'); }
};
