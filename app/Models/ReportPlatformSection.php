<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportPlatformSection extends Model
{
    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'report_id',
        'platform_id',
        'spend',
        'budget',
        'impressions',
        'reach',
        'clicks',
        'link_clicks',
        'ctr',
        'cpm',
        'cpc',
        'conversions',
        'cpa',
        'leads',
        'cpl',
        'video_views',
        'video_completions',
        'vtr',
        'frequency',
        'engagement',
        'performance_vs_benchmark',
        'ai_summary',
        'ai_highlights',
        'ai_concerns',
        'ai_suggested_action',
        'top_performing_ads',
        'worst_performing_ads',
        'human_notes',
        'performance_flags',
    ];

    protected $casts = [
        'spend' => 'decimal:2',
        'budget' => 'decimal:2',
        'ai_highlights' => 'array',
        'ai_concerns' => 'array',
        'top_performing_ads' => 'array',
        'worst_performing_ads' => 'array',
        'performance_flags' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
