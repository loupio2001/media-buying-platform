<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained('campaigns')->cascadeOnDelete();
            $table->string('objective', 200)->nullable();
            $table->jsonb('kpis_requested')->nullable();
            $table->text('target_audience')->nullable();
            $table->jsonb('geo_targeting')->nullable();
            $table->decimal('budget_total', 12, 2)->nullable();
            $table->jsonb('channels_requested')->nullable();
            $table->jsonb('channels_recommended')->nullable();
            $table->jsonb('creative_formats')->nullable();
            $table->date('flight_start')->nullable();
            $table->date('flight_end')->nullable();
            $table->text('constraints')->nullable();
            $table->smallInteger('version')->default(1);
            $table->smallInteger('ai_brief_quality_score')->nullable();
            $table->jsonb('ai_missing_info')->nullable();
            $table->jsonb('ai_kpi_challenges')->nullable();
            $table->jsonb('ai_questions_for_client')->nullable();
            $table->text('ai_channel_rationale')->nullable();
            $table->jsonb('ai_budget_split')->nullable();
            $table->jsonb('ai_media_plan_draft')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE briefs ADD CONSTRAINT chk_briefs_status CHECK (status IN ('draft', 'reviewed', 'approved', 'revision_requested'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('briefs');
    }
};
