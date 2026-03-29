<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ad extends Model
{
    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'ad_set_id',
        'external_id',
        'name',
        'format',
        'creative_url',
        'headline',
        'body',
        'cta',
        'destination_url',
        'status',
        'is_tracked',
    ];

    protected $casts = [
        'is_tracked' => 'boolean',
    ];

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AdSnapshot::class);
    }

    public function scopeTracked($query)
    {
        return $query->where('is_tracked', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function latestSnapshot()
    {
        return $this->snapshots()->where('granularity', 'daily')->orderByDesc('snapshot_date')->first();
    }
}
