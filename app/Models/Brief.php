<?php

namespace App\Models;

use App\Enums\BriefStatus;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Brief extends Model
{
    use HasActivityLog;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'campaign_id',
        'objective',
        'kpis_requested',
        'target_audience',
        'geo_targeting',
        'budget_total',
        'channels_requested',
        'channels_recommended',
        'creative_formats',
        'flight_start',
        'flight_end',
        'constraints',
        'version',
        'ai_brief_quality_score',
        'ai_missing_info',
        'ai_kpi_challenges',
        'ai_questions_for_client',
        'ai_channel_rationale',
        'ai_budget_split',
        'ai_media_plan_draft',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => BriefStatus::class,
        'kpis_requested' => 'array',
        'geo_targeting' => 'array',
        'channels_requested' => 'array',
        'channels_recommended' => 'array',
        'creative_formats' => 'array',
        'ai_missing_info' => 'array',
        'ai_kpi_challenges' => 'array',
        'ai_questions_for_client' => 'array',
        'ai_budget_split' => 'array',
        'ai_media_plan_draft' => 'array',
        'budget_total' => 'decimal:2',
        'flight_start' => 'date',
        'flight_end' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
