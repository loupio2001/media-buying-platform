<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_set_id')->constrained('ad_sets')->cascadeOnDelete();
            $table->string('external_id', 100);
            $table->string('name', 255);
            $table->string('format', 50)->nullable();
            $table->string('creative_url', 500)->nullable();
            $table->text('headline')->nullable();
            $table->text('body')->nullable();
            $table->string('cta', 50)->nullable();
            $table->string('destination_url', 500)->nullable();
            $table->string('status', 30)->default('active');
            $table->boolean('is_tracked')->default(true);
            $table->timestampsTz();

            $table->unique(['ad_set_id', 'external_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ads ADD CONSTRAINT chk_ads_status CHECK (status IN ('active', 'paused', 'deleted', 'archived'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
