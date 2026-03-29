<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('category_id')->constrained('categories');
            $table->string('logo_url', 255)->nullable();
            $table->string('primary_contact', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('agency_lead', 150)->nullable();
            $table->string('country', 50)->default('Morocco');
            $table->string('currency', 10)->default('MAD');
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('billing_type', 20)->default('project');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE clients ADD CONSTRAINT chk_clients_billing_type CHECK (billing_type IN ('retainer', 'project', 'performance'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};