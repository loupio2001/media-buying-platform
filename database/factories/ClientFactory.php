<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'category_id' => Category::factory(),
            'primary_contact' => $this->faker->name(),
            'contact_email' => $this->faker->companyEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'country' => 'Morocco',
            'currency' => 'MAD',
            'billing_type' => $this->faker->randomElement(['retainer', 'project', 'performance']),
            'is_active' => true,
        ];
    }
}
