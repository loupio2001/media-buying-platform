<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->timestampsTz();
        });

        $categories = [
            ['name' => 'Air Travel', 'slug' => 'air-travel'],
            ['name' => 'Banking / Finance', 'slug' => 'banking-finance'],
            ['name' => 'FMCG', 'slug' => 'fmcg'],
            ['name' => 'Hospitality / Hotels', 'slug' => 'hospitality'],
            ['name' => 'Real Estate', 'slug' => 'real-estate'],
            ['name' => 'Telecom', 'slug' => 'telecom'],
            ['name' => 'Retail / E-commerce', 'slug' => 'retail-ecommerce'],
            ['name' => 'Automotive', 'slug' => 'automotive'],
            ['name' => 'Education', 'slug' => 'education'],
            ['name' => 'Government / Public Sector', 'slug' => 'government'],
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

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => null,
                'is_custom' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
