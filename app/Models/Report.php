<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasActivityLog;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'campaign_id',
        'type',
        'period_start',
        'period_end',
        'title',
        'executive_summary',
        'overall_performance',
        'ai_recommendations',
        'status',
        'version',
        'exported_file_path',
        'exported_at',
        'export_format',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'ai_recommendations' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'exported_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function platformSections(): HasMany
    {
        return $this->hasMany(ReportPlatformSection::class);
    }
}
