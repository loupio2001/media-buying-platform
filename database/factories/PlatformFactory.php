<?php

namespace Database\Factories;

use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformFactory extends Factory
{
    protected $model = Platform::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'slug' => $this->faker->unique()->slug(1),
            'api_supported' => $this->faker->boolean(),
            'supports_reach' => $this->faker->boolean(),
            'supports_video_metrics' => $this->faker->boolean(),
            'supports_frequency' => $this->faker->boolean(),
            'supports_leads' => $this->faker->boolean(),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
