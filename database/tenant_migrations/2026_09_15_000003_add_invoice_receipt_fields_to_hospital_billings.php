<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hospital_billings', function (Blueprint $table): void {
            $table->string('invoice_number', 64)->nullable()->after('id');
            $table->string('receipt_number', 64)->nullable()->after('payment_notes');
            $table->string('payment_method', 40)->nullable()->after('receipt_number');
            $table->dateTime('receipt_issued_at')->nullable()->after('payment_method');
        });

        DB::table('hospital_billings')
            ->select(['id', 'restaurant_id'])
            ->whereNull('invoice_number')
            ->orderBy('id')
            ->eachById(function (object $billing): void {
                DB::table('hospital_billings')
                    ->where('id', $billing->id)
                    ->update(['invoice_number' => 'INV-' . $billing->restaurant_id . '-' . $billing->id]);
            });

        DB::table('hospital_billings')
            ->select(['id', 'restaurant_id', 'paid_at'])
            ->where('status', 'paid')
            ->whereNull('receipt_number')
            ->orderBy('id')
            ->eachById(function (object $billing): void {
                DB::table('hospital_billings')->where('id', $billing->id)->update([
                    'receipt_number' => 'RCT-' . $billing->restaurant_id . '-' . $billing->id,
                    'payment_method' => 'other',
                    'receipt_issued_at' => $billing->paid_at,
                ]);
            });

        Schema::table('hospital_billings', function (Blueprint $table): void {
            $table->unique(['restaurant_id', 'invoice_number'], 'hbill_inv_tenant_uq');
            $table->unique(['restaurant_id', 'receipt_number'], 'hbill_rcpt_tenant_uq');
        });
    }

    public function down(): void
    {
        Schema::table('hospital_billings', function (Blueprint $table): void {
            $table->dropUnique('hbill_inv_tenant_uq');
            $table->dropUnique('hbill_rcpt_tenant_uq');
            $table->dropColumn(['invoice_number', 'receipt_number', 'payment_method', 'receipt_issued_at']);
        });
    }
};
