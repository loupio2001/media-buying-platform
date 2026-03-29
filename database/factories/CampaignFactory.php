<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 month', '+2 months');
        $end = $this->faker->dateTimeBetween('+3 months', '+6 months');

        return [
            'client_id' => Client::factory(),
            'name' => $this->faker->words(3, true) . ' Campaign',
            'status' => 'draft',
            'objective' => $this->faker->randomElement(['awareness', 'reach', 'traffic', 'leads', 'conversions']),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'total_budget' => $this->faker->numberBetween(10000, 200000),
            'currency' => 'MAD',
            'pacing_strategy' => 'even',
            'created_by' => User::factory(),
        ];
    }
}
