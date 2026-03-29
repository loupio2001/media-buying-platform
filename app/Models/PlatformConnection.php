<?php

namespace App\Models;

use App\Traits\EncryptsAttributes;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformConnection extends Model
{
    use HasActivityLog;
    use EncryptsAttributes;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected array $encrypted = ['access_token', 'refresh_token', 'api_key'];

    protected $fillable = [
        'platform_id',
        'account_id',
        'account_name',
        'auth_type',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'api_key',
        'extra_credentials',
        'scopes',
        'is_connected',
        'last_sync_at',
        'last_error',
        'error_count',
        'created_by',
    ];

    protected $casts = [
        'extra_credentials' => 'array',
        'scopes' => 'array',
        'is_connected' => 'boolean',
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token', 'api_key', 'extra_credentials'];

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

    public function scopeConnected($query)
    {
        return $query->where('is_connected', true);
    }

    public function scopeHealthy($query)
    {
        return $query->where('is_connected', true)->where('error_count', '<', 5);
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

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
            'error_count' => 0,
            'last_error' => null,
            'last_sync_at' => now(),
        ]);
    }
}
