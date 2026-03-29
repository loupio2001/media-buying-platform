<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms');
            $table->string('account_id', 100);
            $table->string('account_name', 150)->nullable();
            $table->string('auth_type', 20);
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->text('api_key')->nullable();
            $table->jsonb('extra_credentials')->nullable();
            $table->jsonb('scopes')->nullable();
            $table->boolean('is_connected')->default(true);
            $table->timestampTz('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('error_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestampsTz();

            $table->unique(['platform_id', 'account_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE platform_connections ADD CONSTRAINT chk_platform_connections_auth_type CHECK (auth_type IN ('oauth2', 'api_key', 'service_account'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_connections');
    }
};