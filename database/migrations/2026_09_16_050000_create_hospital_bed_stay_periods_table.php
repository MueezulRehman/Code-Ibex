<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_bed_stay_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bed_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->foreignId('hospital_billing_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['restaurant_id', 'hospital_admission_id', 'started_at'], 'hbsp_scope_admission_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('hospital_bed_stay_periods'); }
};
