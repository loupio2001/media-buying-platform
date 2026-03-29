<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryChannelRecommendation extends Model
{
    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'category_id',
        'objective',
        'platform_id',
        'priority',
        'suggested_budget_pct',
        'rationale',
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
