<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('metric', 30);
            $table->decimal('min_value', 12, 4);
            $table->decimal('max_value', 12, 4);
            $table->string('unit', 10);
            $table->integer('sample_size')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['category_id', 'platform_id', 'metric']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE category_benchmarks ADD CONSTRAINT chk_category_benchmarks_range CHECK (max_value >= min_value)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_benchmarks');
    }
};