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
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('hospital_medication_order_id');
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('medicine_batch_id');
            $table->unsignedBigInteger('fulfilled_by');
            $table->unsignedBigInteger('hospital_billing_id')->nullable();
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
