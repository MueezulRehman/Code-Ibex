<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nurse_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->string('shift', 20)->default('morning');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
        });

        Schema::create('nursing_assignment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nursing_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->foreignId('previous_nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('changed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'nursing_assignment_id', 'changed_at'], 'nae_scope_changed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_assignment_events');
        Schema::dropIfExists('nursing_assignments');
    }
};
