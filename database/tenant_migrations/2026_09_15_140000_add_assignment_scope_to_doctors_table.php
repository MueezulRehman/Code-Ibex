<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table): void {
            // Nullable keeps existing doctor records usable until a manager links them.
            // Users live in the central connection, so this cross-connection
            // reference is deliberately an indexed nullable scalar.
            $table->unsignedBigInteger('user_id')->nullable()->after('restaurant_id');
            $table->foreignId('department_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unique(['restaurant_id', 'user_id']);
            $table->index(['restaurant_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table): void {
            $table->dropUnique(['restaurant_id', 'user_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'user_id']);
        });
    }
};
