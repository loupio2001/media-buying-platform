<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    use HasActivityLog, HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'name',
        'slug',
        'icon_url',
        'api_supported',
        'supports_reach',
        'supports_video_metrics',
        'supports_frequency',
        'supports_leads',
        'default_metrics',
        'rate_limit_config',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'api_supported' => 'boolean',
        'supports_reach' => 'boolean',
        'supports_video_metrics' => 'boolean',
        'supports_frequency' => 'boolean',
        'supports_leads' => 'boolean',
        'default_metrics' => 'array',
        'rate_limit_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function connections(): HasMany
    {
        return $this->hasMany(PlatformConnection::class);
    }

    public function benchmarks(): HasMany
    {
        return $this->hasMany(CategoryBenchmark::class);
    }

    public function channelRecommendations(): HasMany
    {
        return $this->hasMany(CategoryChannelRecommendation::class);
    }

    public function campaignPlatforms(): HasMany
    {
        return $this->hasMany(CampaignPlatform::class);
    }

    public function reportSections(): HasMany
    {
        return $this->hasMany(ReportPlatformSection::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApiSupported($query)
    {
        return $query->where('api_supported', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
