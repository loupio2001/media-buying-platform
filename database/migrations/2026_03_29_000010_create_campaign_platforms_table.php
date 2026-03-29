<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms');
            $table->foreignId('platform_connection_id')->nullable()->constrained('platform_connections')->nullOnDelete();
            $table->string('external_campaign_id', 100)->nullable();
            $table->decimal('budget', 12, 2);
            $table->string('budget_type', 10)->default('lifetime');
            $table->string('currency', 10)->default('MAD');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_sync_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['campaign_id', 'platform_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE campaign_platforms ADD CONSTRAINT chk_campaign_platforms_budget_type CHECK (budget_type IN ('lifetime', 'daily'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_platforms');
    }
};
