<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_platform_id')->constrained('campaign_platforms')->cascadeOnDelete();
            $table->string('external_id', 100);
            $table->string('name', 255);
            $table->string('objective', 100)->nullable();
            $table->text('targeting_summary')->nullable();
            $table->string('status', 30)->default('active');
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('budget_type', 10)->nullable();
            $table->string('bid_strategy', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_tracked')->default(true);
            $table->timestampsTz();

            $table->unique(['campaign_platform_id', 'external_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ad_sets ADD CONSTRAINT chk_ad_sets_status CHECK (status IN ('active', 'paused', 'deleted', 'archived'))");
            DB::statement("ALTER TABLE ad_sets ADD CONSTRAINT chk_ad_sets_budget_type CHECK (budget_type IS NULL OR budget_type IN ('lifetime', 'daily'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_sets');
    }
};
