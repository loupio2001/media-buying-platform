[CmdletBinding()]
param(
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [string]$InternalToken = $env:INTERNAL_API_TOKEN,
    [switch]$SkipMigrate
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($InternalToken)) {
    $envFile = Join-Path (Get-Location).Path ".env"
    if (Test-Path $envFile) {
        $tokenLine = Select-String -Path $envFile -Pattern '^INTERNAL_API_TOKEN=' | Select-Object -First 1
        if ($tokenLine) {
            $InternalToken = ($tokenLine.Line -replace '^INTERNAL_API_TOKEN=', '').Trim().Trim('"')
        }
    }
}

if ([string]::IsNullOrWhiteSpace($InternalToken)) {
    throw "INTERNAL_API_TOKEN manquant. Passe -InternalToken ou configure la variable d'environnement."
}

function Invoke-Step {
    param(
        [Parameter(Mandatory = $true)][string]$Label,
        [Parameter(Mandatory = $true)][string]$Command
    )

    Write-Host "`n==> $Label" -ForegroundColor Cyan
    Write-Host "    $Command" -ForegroundColor DarkGray
    Invoke-Expression $Command
}

function Invoke-PhpSnippet {
    param(
        [Parameter(Mandatory = $true)][string]$Code
    )

    $output = & php -r $Code
    if ($LASTEXITCODE -ne 0) {
        throw "Execution PHP -r echouee."
    }

    return ($output | Out-String).Trim()
}

$serverJob = $null

try {
    if (-not $SkipMigrate) {
        Invoke-Step -Label "Reset schema" -Command "php artisan migrate:fresh --seed"
        Invoke-Step -Label "Partitions snapshots" -Command "php artisan partitions:create-monthly --months=3"
    }

    $fixturePhp = @'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Enums\CampaignObjective;
use App\Enums\CampaignStatus;
use App\Enums\PacingStrategy;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Category;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;

$user = User::query()->first();
$category = Category::query()->first();
$platform = Platform::query()->where('slug', 'meta')->first();

if (!$user || !$category || !$platform) {
    throw new RuntimeException('Fixtures prealables introuvables (user/category/platform).');
}

$suffix = date('YmdHis');
$client = Client::query()->create([
    'name' => 'E2E Client ' . $suffix,
    'category_id' => $category->id,
    'is_active' => true,
]);

$campaign = Campaign::query()->create([
    'client_id' => $client->id,
    'name' => 'E2E Campaign ' . $suffix,
    'status' => CampaignStatus::Active->value,
    'objective' => CampaignObjective::Traffic->value,
    'start_date' => now()->subDay()->toDateString(),
    'end_date' => now()->addDays(14)->toDateString(),
    'total_budget' => 10000,
    'currency' => 'MAD',
    'pacing_strategy' => PacingStrategy::Even->value,
    'created_by' => $user->id,
]);

$campaignPlatform = CampaignPlatform::query()->create([
    'campaign_id' => $campaign->id,
    'platform_id' => $platform->id,
    'external_campaign_id' => 'E2E-CAMP-' . $suffix,
    'budget' => 10000,
    'budget_type' => 'lifetime',
    'currency' => 'MAD',
    'is_active' => true,
]);

$adSet = AdSet::query()->create([
    'campaign_platform_id' => $campaignPlatform->id,
    'external_id' => 'E2E-ADSET-' . $suffix,
    'name' => 'E2E AdSet ' . $suffix,
    'status' => 'active',
    'budget' => 1000,
    'budget_type' => 'daily',
    'is_tracked' => true,
]);

$ad = Ad::query()->create([
    'ad_set_id' => $adSet->id,
    'external_id' => 'E2E-AD-' . $suffix,
    'name' => 'E2E Ad ' . $suffix,
    'status' => 'active',
    'is_tracked' => true,
]);

echo json_encode([
    'campaign_id' => $campaign->id,
    'campaign_platform_id' => $campaignPlatform->id,
    'ad_set_id' => $adSet->id,
    'ad_id' => $ad->id,
], JSON_THROW_ON_ERROR);
'@

    Write-Host "`n==> Fixtures E2E" -ForegroundColor Cyan
    $fixture = (Invoke-PhpSnippet -Code $fixturePhp) | ConvertFrom-Json
    Write-Host "    campaign_id=$($fixture.campaign_id) ad_id=$($fixture.ad_id)" -ForegroundColor DarkGray

    $serveCommand = "php artisan serve --host=127.0.0.1 --port=8000"
    Write-Host "`n==> Start server" -ForegroundColor Cyan
    $serverJob = Start-Job -ScriptBlock {
        param($workingDir, $cmd)
        Set-Location $workingDir
        Invoke-Expression $cmd
    } -ArgumentList (Get-Location).Path, $serveCommand

    $ready = $false
    for ($i = 0; $i -lt 30; $i++) {
        try {
            Invoke-WebRequest -Uri "$BaseUrl" -Method Get -TimeoutSec 2 | Out-Null
            $ready = $true
            break
        } catch {
            Start-Sleep -Milliseconds 500
        }
    }

    if (-not $ready) {
        throw "Le serveur Laravel n'a pas demarre dans le delai attendu."
    }

    $snapshotPayload = @{
        snapshots = @(
            @{
                ad_id = [int]$fixture.ad_id
                snapshot_date = (Get-Date).ToString("yyyy-MM-dd")
                granularity = "daily"
                source = "api"
                impressions = 1200
                clicks = 60
                spend = 180.50
                conversions = 4
                leads = 2
                video_views = 250
            },
            @{
                ad_id = [int]$fixture.ad_id
                snapshot_date = (Get-Date).ToString("yyyy-MM-dd")
                granularity = "cumulative"
                source = "api"
                impressions = 1200
                clicks = 60
                spend = 180.50
            }
        )
    }

    Write-Host "`n==> Call internal snapshots/batch" -ForegroundColor Cyan
    $apiResponse = Invoke-RestMethod `
        -Uri "$BaseUrl/api/internal/v1/snapshots/batch" `
        -Method Post `
        -Headers @{ "X-Internal-Token" = $InternalToken } `
        -ContentType "application/json" `
        -Body ($snapshotPayload | ConvertTo-Json -Depth 6)

    $ids = @($apiResponse.data.ids)
    if ($ids.Count -lt 2) {
        throw "L'API interne n'a pas retourne 2 snapshots comme attendu."
    }

    $verifyPhp = @'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdSnapshot;
use App\Services\DashboardSummaryService;

$adId = (int) getenv('E2E_AD_ID');
$dailyCount = AdSnapshot::query()->where('ad_id', $adId)->where('granularity', 'daily')->count();
$cumulativeCount = AdSnapshot::query()->where('ad_id', $adId)->where('granularity', 'cumulative')->count();
$summary = app(DashboardSummaryService::class)->summary();

echo json_encode([
    'daily_count' => $dailyCount,
    'cumulative_count' => $cumulativeCount,
    'summary' => $summary,
], JSON_THROW_ON_ERROR);
'@

    $env:E2E_AD_ID = [string]$fixture.ad_id
    Write-Host "`n==> Verify DB + dashboard summary" -ForegroundColor Cyan
    $verification = (Invoke-PhpSnippet -Code $verifyPhp) | ConvertFrom-Json

    if ([int]$verification.daily_count -lt 1) {
        throw "Aucun snapshot daily trouve pour l'ad de test."
    }

    if ([int]$verification.cumulative_count -lt 1) {
        throw "Aucun snapshot cumulative trouve pour l'ad de test."
    }

    if ([int]$verification.summary.total_campaigns -lt 1) {
        throw "Le resume dashboard ne remonte aucune campagne."
    }

    Write-Host "`nE2E OK" -ForegroundColor Green
    Write-Host "Campaign ID: $($fixture.campaign_id)" -ForegroundColor Green
    Write-Host "Ad ID: $($fixture.ad_id)" -ForegroundColor Green
    Write-Host "Snapshots inserted: $($ids.Count)" -ForegroundColor Green
    Write-Host "Dashboard total_campaigns: $($verification.summary.total_campaigns)" -ForegroundColor Green
} finally {
    Remove-Item Env:E2E_AD_ID -ErrorAction SilentlyContinue

    if ($serverJob) {
        Stop-Job -Job $serverJob -ErrorAction SilentlyContinue | Out-Null
        Receive-Job -Job $serverJob -ErrorAction SilentlyContinue | Out-Null
        Remove-Job -Job $serverJob -Force -ErrorAction SilentlyContinue
    }
}
