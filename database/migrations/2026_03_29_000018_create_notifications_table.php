<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('severity', 10);
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->integer('entity_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->boolean('is_actionable')->default(false);
            $table->string('action_url', 500)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'is_read', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notifications_severity CHECK (severity IN ('info', 'warning', 'critical'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
