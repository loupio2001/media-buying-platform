<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('role', 20)->default('manager');
            $table->string('password', 255);
            $table->string('password_reset_token', 100)->nullable();
            $table->timestampTz('password_reset_expires')->nullable();
            $table->jsonb('notification_preferences')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('admin', 'manager', 'viewer'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
