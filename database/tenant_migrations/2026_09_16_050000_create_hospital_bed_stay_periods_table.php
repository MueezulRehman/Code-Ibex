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
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('hospital_admission_id');
            $table->unsignedBigInteger('bed_id')->nullable();
            $table->unsignedBigInteger('ward_id')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->unsignedBigInteger('hospital_billing_id')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'hospital_admission_id', 'started_at'], 'hbsp_scope_admission_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('hospital_bed_stay_periods'); }
};
