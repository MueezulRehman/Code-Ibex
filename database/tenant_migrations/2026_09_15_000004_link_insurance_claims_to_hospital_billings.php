<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('hospital_billings', 'insurance_claim_id')) {
            Schema::table('hospital_billings', function (Blueprint $table): void {
                $table->unsignedBigInteger('insurance_claim_id')->nullable()->after('hospital_admission_id');
                $table->index(['restaurant_id', 'insurance_claim_id'], 'hbill_claim_idx');
                $table->foreign('insurance_claim_id', 'hbill_claim_fk')
                    ->references('id')->on('insurance_claims')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('hospital_billings', 'insurance_claim_id')) {
            return;
        }

        Schema::table('hospital_billings', function (Blueprint $table): void {
            $table->dropForeign('hbill_claim_fk');
            $table->dropIndex('hbill_claim_idx');
            $table->dropColumn('insurance_claim_id');
        });
    }
};
