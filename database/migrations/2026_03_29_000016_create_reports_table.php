<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns');
            $table->string('type', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('title', 200)->nullable();
            $table->text('executive_summary')->nullable();
            $table->string('overall_performance', 20)->nullable();
            $table->jsonb('ai_recommendations')->nullable();
            $table->string('status', 20)->default('draft');
            $table->smallInteger('version')->default(1);
            $table->string('exported_file_path', 500)->nullable();
            $table->timestampTz('exported_at')->nullable();
            $table->string('export_format', 10)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_reports_type CHECK (type IN ('weekly', 'monthly', 'mid', 'end', 'custom'))");
            DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_reports_overall_performance CHECK (overall_performance IS NULL OR overall_performance IN ('on_track', 'underperforming', 'overperforming'))");
            DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_reports_status CHECK (status IN ('draft', 'reviewed', 'exported'))");
            DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_reports_export_format CHECK (export_format IS NULL OR export_format IN ('pptx', 'pdf', 'both'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
