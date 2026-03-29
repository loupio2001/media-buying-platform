<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSet extends Model
{
    protected $table = 'ad_sets';

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'campaign_platform_id',
        'external_id',
        'name',
        'objective',
        'targeting_summary',
        'status',
        'budget',
        'budget_type',
        'bid_strategy',
        'start_date',
        'end_date',
        'is_tracked',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'is_tracked' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function campaignPlatform(): BelongsTo
    {
        return $this->belongsTo(CampaignPlatform::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function scopeTracked($query)
    {
        return $query->where('is_tracked', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
