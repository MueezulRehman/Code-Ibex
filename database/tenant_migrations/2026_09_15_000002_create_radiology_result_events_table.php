<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('radiology_result_events')) {
            return;
        }

        Schema::create('radiology_result_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('radiology_result_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->json('previous_values')->nullable();
            $table->json('new_values');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('changed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'radiology_result_id', 'changed_at'], 'radiology_results_events_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_result_events');
    }
};
