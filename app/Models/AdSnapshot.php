<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSnapshot extends Model
{
    protected $table = 'ad_snapshots';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'ad_id',
        'snapshot_date',
        'granularity',
        'impressions',
        'reach',
        'frequency',
        'clicks',
        'link_clicks',
        'landing_page_views',
        'ctr',
        'spend',
        'cpm',
        'cpc',
        'conversions',
        'cpa',
        'leads',
        'cpl',
        'video_views',
        'video_completions',
        'vtr',
        'engagement',
        'engagement_rate',
        'thumb_stop_rate',
        'custom_metrics',
        'raw_response',
        'source',
        'pulled_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'spend' => 'decimal:2',
        'ctr' => 'decimal:4',
        'cpm' => 'decimal:4',
        'cpc' => 'decimal:4',
        'cpa' => 'decimal:4',
        'cpl' => 'decimal:4',
        'vtr' => 'decimal:4',
        'frequency' => 'decimal:4',
        'custom_metrics' => 'array',
        'raw_response' => 'array',
        'pulled_at' => 'datetime',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function scopeDaily($query)
    {
        return $query->where('granularity', 'daily');
    }

    public function scopeCumulative($query)
    {
        return $query->where('granularity', 'cumulative');
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('snapshot_date', [$start, $end]);
    }

    public function scopeFromApi($query)
    {
        return $query->where('source', 'api');
    }

    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }
}
