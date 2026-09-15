<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discharge_summary_events')) {
            return;
        }

        Schema::create('discharge_summary_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discharge_summary_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20);
            $table->json('previous_snapshot')->nullable();
            $table->json('new_snapshot');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('changed_at');
            $table->text('correction_reason')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'discharge_summary_id', 'changed_at'], 'discharge_summary_events_tenant_summary_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discharge_summary_events');
    }
};
