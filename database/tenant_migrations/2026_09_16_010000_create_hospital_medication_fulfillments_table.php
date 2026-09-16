<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_medication_fulfillments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'hmf_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('hospital_medication_order_id')->constrained('hospital_medication_orders', 'id', 'hmf_order_fk')->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines', 'id', 'hmf_medicine_fk')->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained('medicine_batches', 'id', 'hmf_batch_fk')->restrictOnDelete();
            $table->foreignId('fulfilled_by')->constrained('users', 'id', 'hmf_fulfilled_by_fk')->restrictOnDelete();
            $table->foreignId('hospital_billing_id')->nullable()->constrained('hospital_billings', 'id', 'hmf_billing_fk')->nullOnDelete();
            $table->decimal('quantity_requested', 14, 3);
            $table->decimal('quantity_dispensed', 14, 3)->default(0);
            $table->string('status', 30)->default('pending');
            $table->dateTime('dispensed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'hospital_medication_order_id'], 'hmf_order_unique');
            $table->index(['restaurant_id', 'status'], 'hmf_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_medication_fulfillments');
    }
};
