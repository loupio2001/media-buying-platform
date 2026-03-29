<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_platform_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms');
            $table->decimal('spend', 12, 2)->default(0);
            $table->decimal('budget', 12, 2)->default(0);
            $table->bigInteger('impressions')->default(0);
            $table->bigInteger('reach')->nullable();
            $table->bigInteger('clicks')->default(0);
            $table->bigInteger('link_clicks')->nullable();
            $table->decimal('ctr', 8, 4)->nullable();
            $table->decimal('cpm', 10, 4)->nullable();
            $table->decimal('cpc', 10, 4)->nullable();
            $table->integer('conversions')->nullable();
            $table->decimal('cpa', 10, 4)->nullable();
            $table->integer('leads')->nullable();
            $table->decimal('cpl', 10, 4)->nullable();
            $table->bigInteger('video_views')->nullable();
            $table->bigInteger('video_completions')->nullable();
            $table->decimal('vtr', 8, 4)->nullable();
            $table->decimal('frequency', 8, 4)->nullable();
            $table->bigInteger('engagement')->nullable();
            $table->string('performance_vs_benchmark', 20)->nullable();
            $table->text('ai_summary')->nullable();
            $table->jsonb('ai_highlights')->nullable();
            $table->jsonb('ai_concerns')->nullable();
            $table->text('ai_suggested_action')->nullable();
            $table->jsonb('top_performing_ads')->nullable();
            $table->jsonb('worst_performing_ads')->nullable();
            $table->text('human_notes')->nullable();
            $table->jsonb('performance_flags')->nullable();
            $table->timestampsTz();

            $table->unique(['report_id', 'platform_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE report_platform_sections ADD CONSTRAINT chk_report_platform_sections_performance_vs_benchmark CHECK (performance_vs_benchmark IS NULL OR performance_vs_benchmark IN ('above', 'within', 'below'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_platform_sections');
    }
};
