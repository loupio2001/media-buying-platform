<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryBenchmark extends Model
{
    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'category_id',
        'platform_id',
        'metric',
        'min_value',
        'max_value',
        'unit',
        'sample_size',
        'last_reviewed_at',
        'notes',
    ];

    protected $casts = [
        'min_value' => 'decimal:4',
        'max_value' => 'decimal:4',
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

    public function evaluate(float $value): string
    {
        if ($value < $this->min_value) {
            return 'below';
        }

        if ($value > $this->max_value) {
            return 'above';
        }

        return 'within';
    }

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
