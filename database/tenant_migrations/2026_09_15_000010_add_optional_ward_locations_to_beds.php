<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ward_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->string('code', 30);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'code']);
            $table->index(['restaurant_id', 'ward_id', 'type']);
        });

        Schema::table('beds', function (Blueprint $table): void {
            $table->foreignId('location_id')
                ->nullable()
                ->after('ward_id')
                ->constrained('ward_locations')
                ->nullOnDelete();
            $table->index(['restaurant_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::table('beds', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['restaurant_id', 'location_id']);
            $table->dropColumn('location_id');
        });

        Schema::dropIfExists('ward_locations');
    }
};
