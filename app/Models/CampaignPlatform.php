<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignPlatform extends Model
{
    use HasActivityLog;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'campaign_id',
        'platform_id',
        'platform_connection_id',
        'external_campaign_id',
        'budget',
        'budget_type',
        'currency',
        'is_active',
        'last_sync_at',
        'notes',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(PlatformConnection::class, 'platform_connection_id');
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePullable($query)
    {
        return $query->where('is_active', true)
            ->whereHas('connection', fn ($q) => $q->healthy())
            ->whereHas('campaign', fn ($q) => $q->active());
    }
}
