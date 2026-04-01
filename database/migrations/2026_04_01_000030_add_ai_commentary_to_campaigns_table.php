<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->text('ai_commentary_summary')->nullable()->after('internal_notes');
            $table->jsonb('ai_commentary_highlights')->nullable()->after('ai_commentary_summary');
            $table->jsonb('ai_commentary_concerns')->nullable()->after('ai_commentary_highlights');
            $table->text('ai_commentary_suggested_action')->nullable()->after('ai_commentary_concerns');
            $table->jsonb('ai_commentary_filters')->nullable()->after('ai_commentary_suggested_action');
            $table->timestampTz('ai_commentary_generated_at')->nullable()->after('ai_commentary_filters');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'ai_commentary_summary',
                'ai_commentary_highlights',
                'ai_commentary_concerns',
                'ai_commentary_suggested_action',
                'ai_commentary_filters',
                'ai_commentary_generated_at',
            ]);
        });
    }
};
