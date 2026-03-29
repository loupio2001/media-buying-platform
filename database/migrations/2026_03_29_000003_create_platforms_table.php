<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->string('icon_url', 255)->nullable();
            $table->boolean('api_supported')->default(false);
            $table->boolean('supports_reach')->default(false);
            $table->boolean('supports_video_metrics')->default(false);
            $table->boolean('supports_frequency')->default(false);
            $table->boolean('supports_leads')->default(false);
            $table->jsonb('default_metrics')->nullable();
            $table->jsonb('rate_limit_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });

        DB::table('platforms')->insert([
            [
                'name' => 'Meta',
                'slug' => 'meta',
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => true,
                'default_metrics' => json_encode([
                    'always' => ['impressions', 'clicks', 'ctr', 'spend', 'cpm', 'cpc'],
                    'optional' => ['reach', 'frequency', 'video_views', 'vtr', 'conversions', 'cpa', 'leads', 'cpl', 'engagement'],
                    'platform_specific' => ['engagement_rate' => ['label' => 'Engagement Rate', 'unit' => '%']],
                ]),
                'rate_limit_config' => json_encode(['requests_per_hour' => 200, 'requests_per_day' => 1000, 'batch_size' => 50, 'cooldown_seconds' => 2]),
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Google Ads',
                'slug' => 'google',
                'api_supported' => true,
                'supports_reach' => false,
                'supports_video_metrics' => true,
                'supports_frequency' => false,
                'supports_leads' => true,
                'default_metrics' => json_encode([
                    'always' => ['impressions', 'clicks', 'ctr', 'spend', 'cpm', 'cpc'],
                    'optional' => ['conversions', 'cpa', 'leads', 'cpl', 'video_views', 'vtr'],
                    'platform_specific' => ['quality_score' => ['label' => 'Quality Score', 'unit' => 'int']],
                ]),
                'rate_limit_config' => json_encode(['requests_per_hour' => 100, 'requests_per_day' => 500, 'batch_size' => 25, 'cooldown_seconds' => 3]),
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'TikTok',
                'slug' => 'tiktok',
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => false,
                'supports_leads' => false,
                'default_metrics' => json_encode([
                    'always' => ['impressions', 'clicks', 'ctr', 'spend', 'cpm', 'cpc'],
                    'optional' => ['reach', 'video_views', 'vtr', 'conversions', 'cpa', 'engagement'],
                    'platform_specific' => ['thumb_stop_rate' => ['label' => 'Thumb Stop Rate', 'unit' => '%']],
                ]),
                'rate_limit_config' => json_encode(['requests_per_hour' => 60, 'requests_per_day' => 300, 'batch_size' => 20, 'cooldown_seconds' => 5]),
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'LinkedIn',
                'slug' => 'linkedin',
                'api_supported' => false,
                'supports_reach' => false,
                'supports_video_metrics' => false,
                'supports_frequency' => false,
                'supports_leads' => true,
                'default_metrics' => json_encode([
                    'always' => ['impressions', 'clicks', 'ctr', 'spend', 'cpm', 'cpc'],
                    'optional' => ['conversions', 'cpa', 'leads', 'cpl', 'engagement'],
                    'platform_specific' => ['engagement_rate' => ['label' => 'Engagement Rate', 'unit' => '%']],
                ]),
                'rate_limit_config' => null,
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'YouTube',
                'slug' => 'youtube',
                'api_supported' => true,
                'supports_reach' => false,
                'supports_video_metrics' => true,
                'supports_frequency' => false,
                'supports_leads' => false,
                'default_metrics' => json_encode([
                    'always' => ['impressions', 'clicks', 'ctr', 'spend', 'cpm', 'cpc'],
                    'optional' => ['video_views', 'vtr'],
                    'platform_specific' => [],
                ]),
                'rate_limit_config' => json_encode(['requests_per_hour' => 100, 'requests_per_day' => 500, 'batch_size' => 25, 'cooldown_seconds' => 3]),
                'is_active' => true,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Snapchat',
                'slug' => 'snapchat',
                'api_supported' => false,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => false,
                'default_metrics' => json_encode([
                    'always' => ['impressions', 'clicks', 'ctr', 'spend', 'cpm', 'cpc'],
                    'optional' => ['reach', 'frequency', 'video_views', 'vtr'],
                    'platform_specific' => [],
                ]),
                'rate_limit_config' => null,
                'is_active' => true,
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};