<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hospital_billing_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('hospital_billing_id');
            $table->unsignedBigInteger('hospital_admission_id');
            $table->string('type', 12);
            $table->decimal('amount', 12, 2);
            $table->string('method', 40);
            $table->string('reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->dateTime('recorded_at');
            $table->string('status', 12)->default('posted');
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'hospital_billing_id'], 'hbp_tenant_bill_idx');
            $table->index(['restaurant_id', 'hospital_admission_id'], 'hbp_tenant_adm_idx');
            $table->index(['restaurant_id', 'status'], 'hbp_tenant_stat_idx');
            $table->foreign('restaurant_id', 'hbp_rest_fk')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->foreign('hospital_billing_id', 'hbp_bill_fk')->references('id')->on('hospital_billings')->restrictOnDelete();
            $table->foreign('hospital_admission_id', 'hbp_adm_fk')->references('id')->on('hospital_admissions')->restrictOnDelete();
            $table->foreign('recorded_by', 'hbp_rec_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('voided_by', 'hbp_void_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_billing_payments');
    }
};
