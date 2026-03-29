<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'user_id',
        'type',
        'severity',
        'title',
        'message',
        'entity_type',
        'entity_id',
        'meta',
        'is_read',
        'read_at',
        'is_dismissed',
        'is_actionable',
        'action_url',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_read' => 'boolean',
        'is_dismissed' => 'boolean',
        'is_actionable' => 'boolean',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

    public function markRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    public function dismiss(): void
    {
        $this->update(['is_dismissed' => true]);
    }
}
