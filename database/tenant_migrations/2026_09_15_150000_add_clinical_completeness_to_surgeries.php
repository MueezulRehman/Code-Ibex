<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surgeries', function (Blueprint $table): void {
            $table->foreignId('anaesthetist_id')->nullable()->after('surgeon_id')->constrained('users')->nullOnDelete();
            $table->foreignId('assistant_surgeon_id')->nullable()->after('anaesthetist_id')->constrained('users')->nullOnDelete();
            $table->text('surgical_team_details')->nullable()->after('assistant_surgeon_id');
            $table->string('consent_status', 30)->default('pending')->after('status');
            $table->string('preop_status', 30)->default('pending')->after('consent_status');
            $table->json('preop_checklist')->nullable()->after('preop_status');
            $table->text('postoperative_notes')->nullable()->after('preop_checklist');
            $table->dateTime('completed_at')->nullable()->after('postoperative_notes');
            $table->index(['restaurant_id', 'consent_status', 'preop_status'], 'surgeries_readiness_idx');
        });
    }

    public function down(): void
    {
        Schema::table('surgeries', function (Blueprint $table): void {
            $table->dropIndex('surgeries_readiness_idx');
            $table->dropForeign(['anaesthetist_id']);
            $table->dropForeign(['assistant_surgeon_id']);
            $table->dropColumn([
                'anaesthetist_id', 'assistant_surgeon_id', 'surgical_team_details',
                'consent_status', 'preop_status', 'preop_checklist',
                'postoperative_notes', 'completed_at',
            ]);
        });
    }
};
