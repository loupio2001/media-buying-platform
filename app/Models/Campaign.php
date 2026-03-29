<?php

namespace App\Models;

use App\Enums\CampaignObjective;
use App\Enums\CampaignStatus;
use App\Enums\PacingStrategy;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Campaign extends Model
{
    use HasActivityLog, HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'client_id',
        'name',
        'status',
        'objective',
        'start_date',
        'end_date',
        'total_budget',
        'currency',
        'kpi_targets',
        'pacing_strategy',
        'sheet_id',
        'sheet_url',
        'brief_raw',
        'internal_notes',
        'created_by',
    ];

    protected $casts = [
        'status' => CampaignStatus::class,
        'objective' => CampaignObjective::class,
        'pacing_strategy' => PacingStrategy::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'total_budget' => 'decimal:2',
        'kpi_targets' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaignPlatforms(): HasMany
    {
        return $this->hasMany(CampaignPlatform::class);
    }

    public function brief(): HasOne
    {
        return $this->hasOne(Brief::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', CampaignStatus::Active);
    }

    public function scopeRunning($query)
    {
        return $query->whereIn('status', [CampaignStatus::Active, CampaignStatus::Paused]);
    }

    public function scopeForDashboard($query)
    {
        return $query->whereNotIn('status', [CampaignStatus::Archived]);
    }

    public function totalDays(): int
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    public function daysElapsed(): int
    {
        return max(0, $this->start_date->diffInDays(min(now(), $this->end_date)));
    }

    public function daysRemaining(): int
    {
        return max(0, now()->diffInDays($this->end_date, false));
    }

    public function progressPct(): float
    {
        $total = $this->totalDays();

        return $total > 0 ? round($this->daysElapsed() / $total * 100, 2) : 0;
    }
}
