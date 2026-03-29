<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasActivityLog, HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sO';

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

    public function benchmarksForPlatform(int $platformId)
    {
        return $this->benchmarks()->where('platform_id', $platformId)->get()->keyBy('metric');
    }
}
