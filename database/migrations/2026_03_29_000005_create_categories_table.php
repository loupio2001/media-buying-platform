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