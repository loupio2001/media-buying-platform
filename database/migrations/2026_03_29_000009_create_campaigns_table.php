<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients');
            $table->string('name', 200);
            $table->string('status', 20)->default('draft');
            $table->string('objective', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_budget', 12, 2);
            $table->string('currency', 10)->default('MAD');
            $table->jsonb('kpi_targets')->nullable();
            $table->string('pacing_strategy', 20)->default('even');
            $table->string('sheet_id', 100)->nullable();
            $table->string('sheet_url', 255)->nullable();
            $table->text('brief_raw')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestampsTz();

            $table->index('client_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE campaigns ADD CONSTRAINT chk_campaigns_status CHECK (status IN ('draft', 'active', 'paused', 'ended', 'archived'))");
            DB::statement("ALTER TABLE campaigns ADD CONSTRAINT chk_campaigns_objective CHECK (objective IN ('awareness', 'reach', 'traffic', 'leads', 'conversions', 'engagement', 'app_installs', 'video_views'))");
            DB::statement("ALTER TABLE campaigns ADD CONSTRAINT chk_campaigns_pacing_strategy CHECK (pacing_strategy IN ('even', 'front_loaded', 'back_loaded', 'custom'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
