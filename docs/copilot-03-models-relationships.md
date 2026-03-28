# Copilot Instructions — Part 3: Eloquent Models, Relationships & Traits

**Project:** Havas Media Buying Platform  
**Reference:** `havas-data-model-v3.1.md`  
**This file covers:** All Eloquent models, relationships, casts, scopes, and reusable traits

---

## RULES FOR ALL MODELS

1. Every model uses `timestampsTz` — set `protected $dateFormat = 'Y-m-d H:i:sO'`
2. Every model explicitly defines `$fillable` — no `$guarded = []`
3. Use PHP 8.1 backed enums for `$casts` on status/type fields
4. All JSONB fields cast to `array`
5. No business logic in models — use Services. Models only define structure, relationships, scopes, and casts
6. Apply `HasActivityLog` trait to all models except `ActivityLog`, `Notification`, and `AdSnapshot`

---

## Trait 1: `HasActivityLog`

**File:** `app/Traits/HasActivityLog.php`

```php
<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

trait HasActivityLog
{
    public static function bootHasActivityLog(): void
    {
        static::created(function (Model $model) {
            static::logActivity($model, 'created');
        });

        static::updated(function (Model $model) {
            $changes = [];
            foreach ($model->getDirty() as $field => $newValue) {
                if (in_array($field, ['updated_at', 'created_at'])) continue;
                $changes[$field] = [
                    'old' => $model->getOriginal($field),
                    'new' => $newValue,
                ];
            }
            if (!empty($changes)) {
                static::logActivity($model, 'updated', $changes);
            }
        });

        static::deleted(function (Model $model) {
            static::logActivity($model, 'deleted');
        });
    }

    protected static function logActivity(Model $model, string $action, ?array $changes = null): void
    {
        $user = auth()->user();

        ActivityLog::create([
            'user_id'     => $user?->id,
            'action'      => $action,
            'entity_type' => $model->getTable(),
            'entity_id'   => $model->getKey(),
            'entity_name' => $model->name ?? $model->title ?? null,
            'changes'     => $changes,
            'ip_address'  => request()?->ip(),
            'user_agent'  => request()?->userAgent(),
        ]);
    }
}
```

---

## Trait 2: `EncryptsAttributes`

**File:** `app/Traits/EncryptsAttributes.php`

Used by `PlatformConnection` to auto-encrypt/decrypt sensitive fields.

```php
<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait EncryptsAttributes
{
    /**
     * Define encrypted attributes in the model:
     * protected array $encrypted = ['access_token', 'refresh_token', 'api_key'];
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->encrypted ?? []) && $value !== null) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value; // Return raw if decryption fails (legacy data)
            }
        }

        return $value;
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encrypted ?? []) && $value !== null) {
            $value = Crypt::encryptString($value);
        }

        return parent::setAttribute($key, $value);
    }
}
```

---

## Model: `Platform`

**File:** `app/Models/Platform.php`

```php
<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    use HasActivityLog;

    protected $fillable = [
        'name', 'slug', 'icon_url', 'api_supported',
        'supports_reach', 'supports_video_metrics', 'supports_frequency', 'supports_leads',
        'default_metrics', 'rate_limit_config', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'api_supported'          => 'boolean',
        'supports_reach'         => 'boolean',
        'supports_video_metrics' => 'boolean',
        'supports_frequency'     => 'boolean',
        'supports_leads'         => 'boolean',
        'default_metrics'        => 'array',
        'rate_limit_config'      => 'array',
        'is_active'              => 'boolean',
    ];

    // --- Relationships ---

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

    // --- Scopes ---

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
```

---

## Model: `PlatformConnection`

**File:** `app/Models/PlatformConnection.php`

```php
<?php

namespace App\Models;

use App\Traits\{HasActivityLog, EncryptsAttributes};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PlatformConnection extends Model
{
    use HasActivityLog, EncryptsAttributes;

    protected array $encrypted = ['access_token', 'refresh_token', 'api_key'];

    protected $fillable = [
        'platform_id', 'account_id', 'account_name', 'auth_type',
        'access_token', 'refresh_token', 'token_expires_at', 'api_key',
        'extra_credentials', 'scopes', 'is_connected',
        'last_sync_at', 'last_error', 'error_count', 'created_by',
    ];

    protected $casts = [
        'extra_credentials' => 'array',
        'scopes'            => 'array',
        'is_connected'      => 'boolean',
        'token_expires_at'  => 'datetime',
        'last_sync_at'      => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token', 'api_key', 'extra_credentials'];

    // --- Relationships ---

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaignPlatforms(): HasMany
    {
        return $this->hasMany(CampaignPlatform::class);
    }

    // --- Scopes ---

    public function scopeConnected($query)
    {
        return $query->where('is_connected', true);
    }

    public function scopeHealthy($query)
    {
        return $query->where('is_connected', true)->where('error_count', '<', 5);
    }

    // --- Helpers ---

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) return false;
        return $this->token_expires_at->isPast();
    }

    public function recordError(string $message): void
    {
        $this->increment('error_count');
        $this->update(['last_error' => $message]);

        if ($this->error_count >= 5) {
            $this->update(['is_connected' => false]);
        }
    }

    public function recordSuccess(): void
    {
        $this->update([
            'error_count'  => 0,
            'last_error'   => null,
            'last_sync_at' => now(),
        ]);
    }
}
```

---

## Model: `Category`

**File:** `app/Models/Category.php`

```php
<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasActivityLog;

    protected $fillable = ['name', 'slug', 'description', 'is_custom'];

    protected $casts = ['is_custom' => 'boolean'];

    public function benchmarks(): HasMany
    {
        return $this->hasMany(CategoryBenchmark::class);
    }

    public function channelRecommendations(): HasMany
    {
        return $this->hasMany(CategoryChannelRecommendation::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    // Get benchmarks for a specific platform
    public function benchmarksForPlatform(int $platformId)
    {
        return $this->benchmarks()->where('platform_id', $platformId)->get()->keyBy('metric');
    }
}
```

---

## Model: `CategoryBenchmark`

**File:** `app/Models/CategoryBenchmark.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryBenchmark extends Model
{
    protected $fillable = [
        'category_id', 'platform_id', 'metric',
        'min_value', 'max_value', 'unit',
        'sample_size', 'last_reviewed_at', 'notes',
    ];

    protected $casts = [
        'min_value'        => 'decimal:4',
        'max_value'        => 'decimal:4',
        'last_reviewed_at' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Check if a given value is within benchmark range.
     * Returns: 'above', 'within', 'below'
     */
    public function evaluate(float $value): string
    {
        if ($value < $this->min_value) return 'below';
        if ($value > $this->max_value) return 'above';
        return 'within';
    }

    /**
     * Calculate deviation percentage from minimum benchmark.
     * Negative = below benchmark, Positive = above max.
     */
    public function deviationPct(float $value): float
    {
        if ($value < $this->min_value) {
            return round(($value - $this->min_value) / $this->min_value * 100, 2);
        }
        if ($value > $this->max_value) {
            return round(($value - $this->max_value) / $this->max_value * 100, 2);
        }
        return 0;
    }
}
```

---

## Model: `CategoryChannelRecommendation`

**File:** `app/Models/CategoryChannelRecommendation.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryChannelRecommendation extends Model
{
    protected $fillable = [
        'category_id', 'objective', 'platform_id',
        'priority', 'suggested_budget_pct', 'rationale',
    ];

    protected $casts = [
        'suggested_budget_pct' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
```

---

## Model: `Client`

**File:** `app/Models/Client.php`

```php
<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Client extends Model
{
    use HasActivityLog;

    protected $fillable = [
        'name', 'category_id', 'logo_url',
        'primary_contact', 'contact_email', 'contact_phone',
        'agency_lead', 'country', 'currency',
        'contract_start', 'contract_end', 'billing_type',
        'notes', 'is_active',
    ];

    protected $casts = [
        'contract_start' => 'date',
        'contract_end'   => 'date',
        'is_active'      => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isContractExpiringSoon(int $days = 30): bool
    {
        if (!$this->contract_end) return false;
        return $this->contract_end->between(now(), now()->addDays($days));
    }
}
```

---

## Model: `Campaign`

**File:** `app/Models/Campaign.php`

```php
<?php

namespace App\Models;

use App\Enums\{CampaignStatus, CampaignObjective, PacingStrategy};
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Campaign extends Model
{
    use HasActivityLog;

    protected $fillable = [
        'client_id', 'name', 'status', 'objective',
        'start_date', 'end_date', 'total_budget', 'currency',
        'kpi_targets', 'pacing_strategy',
        'sheet_id', 'sheet_url', 'brief_raw', 'internal_notes',
        'created_by',
    ];

    protected $casts = [
        'status'          => CampaignStatus::class,
        'objective'       => CampaignObjective::class,
        'pacing_strategy' => PacingStrategy::class,
        'start_date'      => 'date',
        'end_date'        => 'date',
        'total_budget'    => 'decimal:2',
        'kpi_targets'     => 'array',
    ];

    // --- Relationships ---

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

    // --- Scopes ---

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

    // --- Helpers ---

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
```

---

## Model: `CampaignPlatform`

**File:** `app/Models/CampaignPlatform.php`

```php
<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class CampaignPlatform extends Model
{
    use HasActivityLog;

    protected $fillable = [
        'campaign_id', 'platform_id', 'platform_connection_id',
        'external_campaign_id', 'budget', 'budget_type', 'currency',
        'is_active', 'last_sync_at', 'notes',
    ];

    protected $casts = [
        'budget'       => 'decimal:2',
        'is_active'    => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    // --- Relationships ---

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

    // --- Scopes ---

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
```

---

## Model: `AdSet`

**File:** `app/Models/AdSet.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class AdSet extends Model
{
    protected $table = 'ad_sets';

    protected $fillable = [
        'campaign_platform_id', 'external_id', 'name', 'objective',
        'targeting_summary', 'status', 'budget', 'budget_type',
        'bid_strategy', 'start_date', 'end_date', 'is_tracked',
    ];

    protected $casts = [
        'budget'     => 'decimal:2',
        'is_tracked' => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
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
```

---

## Model: `Ad`

**File:** `app/Models/Ad.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Ad extends Model
{
    protected $fillable = [
        'ad_set_id', 'external_id', 'name', 'format',
        'creative_url', 'headline', 'body', 'cta',
        'destination_url', 'status', 'is_tracked',
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
        return $this->snapshots()
            ->where('granularity', 'daily')
            ->orderByDesc('snapshot_date')
            ->first();
    }
}
```

---

## Model: `AdSnapshot`

**File:** `app/Models/AdSnapshot.php`

No `HasActivityLog` trait — too many rows, would flood the log.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSnapshot extends Model
{
    protected $table = 'ad_snapshots';

    public $timestamps = false; // Uses pulled_at instead

    protected $fillable = [
        'ad_id', 'snapshot_date', 'granularity',
        'impressions', 'reach', 'frequency', 'clicks', 'link_clicks',
        'landing_page_views', 'ctr', 'spend', 'cpm', 'cpc',
        'conversions', 'cpa', 'leads', 'cpl',
        'video_views', 'video_completions', 'vtr',
        'engagement', 'engagement_rate', 'thumb_stop_rate',
        'custom_metrics', 'raw_response', 'source', 'pulled_at',
    ];

    protected $casts = [
        'snapshot_date'  => 'date',
        'spend'          => 'decimal:2',
        'ctr'            => 'decimal:4',
        'cpm'            => 'decimal:4',
        'cpc'            => 'decimal:4',
        'cpa'            => 'decimal:4',
        'cpl'            => 'decimal:4',
        'vtr'            => 'decimal:4',
        'frequency'      => 'decimal:4',
        'custom_metrics' => 'array',
        'raw_response'   => 'array',
        'pulled_at'      => 'datetime',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    // --- Scopes ---

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
```

---

## Model: `Brief`

**File:** `app/Models/Brief.php`

```php
<?php

namespace App\Models;

use App\Enums\BriefStatus;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Brief extends Model
{
    use HasActivityLog;

    protected $fillable = [
        'campaign_id', 'objective', 'kpis_requested', 'target_audience',
        'geo_targeting', 'budget_total', 'channels_requested', 'channels_recommended',
        'creative_formats', 'flight_start', 'flight_end', 'constraints', 'version',
        'ai_brief_quality_score', 'ai_missing_info', 'ai_kpi_challenges',
        'ai_questions_for_client', 'ai_channel_rationale',
        'ai_budget_split', 'ai_media_plan_draft',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'status'                => BriefStatus::class,
        'kpis_requested'        => 'array',
        'geo_targeting'         => 'array',
        'channels_requested'    => 'array',
        'channels_recommended'  => 'array',
        'creative_formats'      => 'array',
        'ai_missing_info'       => 'array',
        'ai_kpi_challenges'     => 'array',
        'ai_questions_for_client' => 'array',
        'ai_budget_split'       => 'array',
        'ai_media_plan_draft'   => 'array',
        'budget_total'          => 'decimal:2',
        'flight_start'          => 'date',
        'flight_end'            => 'date',
        'reviewed_at'           => 'datetime',
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
```

---

## Model: `Report`

**File:** `app/Models/Report.php`

```php
<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Report extends Model
{
    use HasActivityLog;

    protected $fillable = [
        'campaign_id', 'type', 'period_start', 'period_end',
        'title', 'executive_summary', 'overall_performance',
        'ai_recommendations', 'status', 'version',
        'exported_file_path', 'exported_at', 'export_format',
        'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'ai_recommendations' => 'array',
        'period_start'       => 'date',
        'period_end'         => 'date',
        'exported_at'        => 'datetime',
        'reviewed_at'        => 'datetime',
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
```

---

## Model: `ReportPlatformSection`

**File:** `app/Models/ReportPlatformSection.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportPlatformSection extends Model
{
    protected $fillable = [
        'report_id', 'platform_id',
        'spend', 'budget', 'impressions', 'reach', 'clicks', 'link_clicks',
        'ctr', 'cpm', 'cpc', 'conversions', 'cpa', 'leads', 'cpl',
        'video_views', 'video_completions', 'vtr', 'frequency', 'engagement',
        'performance_vs_benchmark',
        'ai_summary', 'ai_highlights', 'ai_concerns', 'ai_suggested_action',
        'top_performing_ads', 'worst_performing_ads',
        'human_notes', 'performance_flags',
    ];

    protected $casts = [
        'spend'               => 'decimal:2',
        'budget'              => 'decimal:2',
        'ai_highlights'       => 'array',
        'ai_concerns'         => 'array',
        'top_performing_ads'  => 'array',
        'worst_performing_ads' => 'array',
        'performance_flags'   => 'array',
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
```

---

## Model: `User`

**File:** `app/Models/User.php`

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name', 'email', 'role', 'password',
        'password_reset_token', 'password_reset_expires',
        'notification_preferences', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'password_reset_token'];

    protected $casts = [
        'role'                      => UserRole::class,
        'notification_preferences'  => 'array',
        'is_active'                 => 'boolean',
        'password_reset_expires'    => 'datetime',
        'last_login_at'             => 'datetime',
    ];

    // --- Relationships ---

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'created_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // --- Helpers ---

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function wantsNotification(string $type): bool
    {
        $prefs = $this->notification_preferences ?? [];
        return $prefs[$type] ?? true; // Default: receive all
    }

    public function unreadNotificationCount(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }
}
```

---

## Model: `Notification` (Custom)

**File:** `app/Models/Notification.php`

**IMPORTANT:** This is NOT Laravel's built-in `Illuminate\Notifications\DatabaseNotification`. It's a custom model for our notifications table.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false; // Only has created_at

    protected $fillable = [
        'user_id', 'type', 'severity', 'title', 'message',
        'entity_type', 'entity_id', 'meta',
        'is_read', 'read_at', 'is_dismissed',
        'is_actionable', 'action_url', 'expires_at', 'created_at',
    ];

    protected $casts = [
        'meta'        => 'array',
        'is_read'     => 'boolean',
        'is_dismissed' => 'boolean',
        'is_actionable' => 'boolean',
        'read_at'     => 'datetime',
        'expires_at'  => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scopes ---

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_dismissed', false)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    // --- Helpers ---

    public function markRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    public function dismiss(): void
    {
        $this->update(['is_dismissed' => true]);
    }
}
```

---

## Model: `ActivityLog`

**File:** `app/Models/ActivityLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'entity_name', 'changes', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForEntity($query, string $type, int $id)
    {
        return $query->where('entity_type', $type)->where('entity_id', $id);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
```

---

## Next Steps

Proceed to **Part 4:** Observers, services, and business logic.
