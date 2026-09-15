<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hospital_staff_rosters')) {
            return;
        }

        Schema::create('hospital_staff_rosters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('staff_user_id');
            $table->date('shift_date');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('shift_type', 20);
            $table->string('status', 20)->default('scheduled');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('ward_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('restaurant_id', 'hsr_rest_fk')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->foreign('staff_user_id', 'hsr_staff_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('department_id', 'hsr_dept_fk')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('ward_id', 'hsr_ward_fk')->references('id')->on('wards')->nullOnDelete();
            $table->index(['restaurant_id', 'shift_date'], 'hsr_rest_date_idx');
            $table->index(['restaurant_id', 'staff_user_id', 'starts_at'], 'hsr_staff_start_idx');
            $table->index(['restaurant_id', 'status'], 'hsr_rest_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_staff_rosters');
    }
};
