<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasActivityLog, HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'name',
        'category_id',
        'logo_url',
        'primary_contact',
        'contact_email',
        'contact_phone',
        'agency_lead',
        'country',
        'currency',
        'contract_start',
        'contract_end',
        'billing_type',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'contract_start' => 'date',
        'contract_end' => 'date',
        'is_active' => 'boolean',
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
        if (!$this->contract_end) {
            return false;
        }

        return $this->contract_end->between(now(), now()->addDays($days));
    }
}
