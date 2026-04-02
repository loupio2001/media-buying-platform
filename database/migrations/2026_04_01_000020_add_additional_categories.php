<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $categories = [
            ['name' => 'Construction', 'slug' => 'construction'],
            ['name' => 'Pharmaceutical / Medical', 'slug' => 'pharmaceutical-medical'],
            ['name' => 'Research & Development', 'slug' => 'research-development'],
            ['name' => 'Research', 'slug' => 'research'],
            ['name' => 'Energy / Utilities', 'slug' => 'energy-utilities'],
            ['name' => 'Insurance', 'slug' => 'insurance'],
            ['name' => 'Technology / SaaS', 'slug' => 'technology-saas'],
            ['name' => 'Beauty / Cosmetics', 'slug' => 'beauty-cosmetics'],
            ['name' => 'Food & Beverage', 'slug' => 'food-beverage'],
            ['name' => 'Logistics / Transportation', 'slug' => 'logistics-transportation'],
            ['name' => 'Luxury', 'slug' => 'luxury'],
            ['name' => 'Sports / Fitness', 'slug' => 'sports-fitness'],
        ];

        $payload = array_map(static fn (array $category) => [
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => null,
            'is_custom' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ], $categories);

        DB::table('categories')->upsert(
            $payload,
            ['slug'],
            ['name', 'updated_at']
        );
    }

    public function down(): void
    {
        // No-op to avoid rollback failures when categories are referenced by clients.
    }
};
