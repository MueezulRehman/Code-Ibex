<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_medication_fulfillment_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'hmfb_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('hospital_medication_fulfillment_id')->constrained('hospital_medication_fulfillments', 'id', 'hmfb_fulfillment_fk')->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->constrained('medicine_batches', 'id', 'hmfb_batch_fk')->restrictOnDelete();
            $table->decimal('quantity_dispensed', 14, 3);
            $table->timestamps();
            $table->unique(['restaurant_id', 'hospital_medication_fulfillment_id', 'medicine_batch_id'], 'hmfb_allocation_unique');
            $table->index(['restaurant_id', 'medicine_batch_id'], 'hmfb_scope_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_medication_fulfillment_batches');
    }
};
