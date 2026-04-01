#!/usr/bin/env php
<?php

// Simple script to create test campaign data for syncing
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📊 Campaign Setup for Data Syncing\n";
echo "==================================\n\n";

// Check existing data
$campaignCount = DB::table('campaigns')->count();
$cpCount = DB::table('campaign_platforms')->count();
$platformCount = DB::table('platforms')->count();

echo "Current state:\n";
echo "  Campaigns: $campaignCount\n";
echo "  Campaign Platforms: $cpCount\n";
echo "  Platforms: $platformCount\n\n";

if ($platformCount === 0) {
    echo "❌ No platforms found. Please create platforms first.\n";
    exit(1);
}

// Get or create Meta platform
$metaPlatform = DB::table('platforms')->where('name', 'Meta')->first();
if (!$metaPlatform) {
    $metaId = DB::table('platforms')->insertGetId([
        'name' => 'Meta',
        'slug' => 'meta',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $metaPlatform = (object)['id' => $metaId, 'name' => 'Meta'];
    echo "✓ Created Meta platform\n";
}

// Get or create client
$client = DB::table('clients')->first();
if (!$client) {
    $clientId = DB::table('clients')->insertGetId([
        'name' => 'Test Client',
        'country' => 'Morocco',
        'currency' => 'MAD',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $client = (object)['id' => $clientId, 'name' => 'Test Client'];
    echo "✓ Created test client\n";
}

// Create test campaign
$campaign = DB::table('campaigns')->where('name', 'Test Campaign - Sync Demo')->first();
if (!$campaign) {
    $campaignId = DB::table('campaigns')->insertGetId([
        'client_id' => $client->id,
        'name' => 'Test Campaign - Sync Demo',
        'status' => 'active',
        'objective' => 'traffic',
        'start_date' => \Carbon\Carbon::now()->subDays(30)->toDateString(),
        'end_date' => \Carbon\Carbon::now()->addDays(30)->toDateString(),
        'total_budget' => 10000,
        'currency' => 'MAD',
        'pacing_strategy' => 'even',
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $campaign = (object)['id' => $campaignId, 'name' => 'Test Campaign - Sync Demo'];
    echo "✓ Created test campaign\n";
} else {
    echo "✓ Test campaign already exists\n";
}

// Create campaign_platform link
$cp = DB::table('campaign_platforms')
    ->where('campaign_id', $campaign->id)
    ->where('platform_id', $metaPlatform->id)
    ->first();

if (!$cp) {
    $cpId = DB::table('campaign_platforms')->insertGetId([
        'campaign_id' => $campaign->id,
        'platform_id' => $metaPlatform->id,
        'platform_connection_id' => null,
        'external_campaign_id' => 'test-campaign-meta-id',
        'budget' => 5000,
        'budget_type' => 'daily',
        'currency' => 'MAD',
        'is_active' => true,
        'is_tracked' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $cp = (object)['id' => $cpId];
    echo "✓ Created campaign-platform link\n";
} else {
    echo "✓ Campaign-platform link already exists\n";
}

echo "\n✅ Setup complete!\n\n";
echo "📋 Next steps:\n";
echo "1. Replace 'test-campaign-meta-id' with your actual Meta campaign ID\n";
echo "   UPDATE campaign_platforms SET external_campaign_id='YOUR_META_CAMPAIGN_ID' WHERE id=$cp->id\n\n";
echo "2. Go to http://localhost:8000/campaigns and view the test campaign\n";
echo "3. Go to http://localhost:8000/settings/platform-connections\n";
echo "4. Click 'Force Sync' to pull data from Meta\n";
echo "5. Check dashboard and campaign pages for synced metrics\n";
