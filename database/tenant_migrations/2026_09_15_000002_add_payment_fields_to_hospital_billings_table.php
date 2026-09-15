<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hospital_billings', function (Blueprint $table): void {
            $table->string('payment_reference', 255)->nullable()->after('paid_at');
            $table->text('payment_notes')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('hospital_billings', function (Blueprint $table): void {
            $table->dropColumn(['payment_reference', 'payment_notes']);
        });
    }
};
