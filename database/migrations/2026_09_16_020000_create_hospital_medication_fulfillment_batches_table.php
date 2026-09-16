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
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('hospital_medication_fulfillment_id');
            $table->unsignedBigInteger('medicine_batch_id');
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
