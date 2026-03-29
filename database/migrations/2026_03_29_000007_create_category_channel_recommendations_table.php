<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_channel_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('objective', 50);
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('priority', 20);
            $table->decimal('suggested_budget_pct', 5, 2)->nullable();
            $table->text('rationale')->nullable();
            $table->timestampsTz();

            $table->unique(['category_id', 'objective', 'platform_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE category_channel_recommendations ADD CONSTRAINT chk_category_channel_recommendations_priority CHECK (priority IN ('primary', 'secondary'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_channel_recommendations');
    }
};