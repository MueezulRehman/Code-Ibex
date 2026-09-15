<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The test/shared schema keeps clinical tables on the default
        // connection; tenant deployments use the matching tenant migration.
        if (! Schema::hasTable('doctors') || Schema::hasColumn('doctors', 'user_id')) {
            return;
        }

        Schema::table('doctors', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->after('restaurant_id');
            $table->foreignId('department_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unique(['restaurant_id', 'user_id']);
            $table->index(['restaurant_id', 'department_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('doctors') || ! Schema::hasColumn('doctors', 'user_id')) {
            return;
        }

        Schema::table('doctors', function (Blueprint $table): void {
            $table->dropUnique(['restaurant_id', 'user_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'user_id']);
        });
    }
};
