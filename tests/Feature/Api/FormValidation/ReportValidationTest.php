<?php

namespace Tests\Feature\Api\FormValidation;

use App\Events\ReportCreated;
use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Category;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReportValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = Category::firstOrCreate(['slug' => 'fmcg'], ['name' => 'FMCG']);
        $client = Client::factory()->create(['category_id' => $category->id]);
        $this->campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_create_report_requires_campaign_id(): void
    {
        $response = $this->postJson('/api/reports', [
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['campaign_id']);
    }

    public function test_create_report_requires_type(): void
    {
        $response = $this->postJson('/api/reports', [
            'campaign_id' => $this->campaign->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_report_type_must_be_valid_enum(): void
    {
        $response = $this->postJson('/api/reports', [
            'campaign_id' => $this->campaign->id,
            'type' => 'quarterly',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_report_period_end_must_be_after_start(): void
    {
        $response = $this->postJson('/api/reports', [
            'campaign_id' => $this->campaign->id,
            'type' => 'mid',
            'period_start' => '2026-05-15',
            'period_end' => '2026-04-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period_end']);
    }

    public function test_create_report_overall_performance_must_be_valid(): void
    {
        $response = $this->postJson('/api/reports', [
            'campaign_id' => $this->campaign->id,
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
            'overall_performance' => 'excellent',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['overall_performance']);
    }

    public function test_create_report_status_must_be_valid(): void
    {
        $response = $this->postJson('/api/reports', [
            'campaign_id' => $this->campaign->id,
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
            'status' => 'published',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_create_report_with_valid_data_returns_201(): void
    {
        $response = $this->postJson('/api/reports', [
            'campaign_id' => $this->campaign->id,
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
            'title' => 'Mid-Campaign Report — Ramadan 2026',
            'status' => 'draft',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_create_report_generates_platform_sections_for_active_campaign_platforms(): void
    {
        Event::fake([ReportCreated::class]);

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE VIEW IF NOT EXISTS v_campaign_platform_totals AS SELECT NULL AS campaign_platform_id, NULL AS calc_ctr, NULL AS calc_cpm, NULL AS calc_cpc, NULL AS calc_cpa, NULL AS calc_cpl, NULL AS calc_vtr, NULL AS calc_frequency WHERE 0 = 1');
        }

        $platform = Platform::factory()->create();

        $campaignPlatform = CampaignPlatform::create([
            'campaign_id' => $this->campaign->id,
            'platform_id' => $platform->id,
            'budget' => 15000,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reports', [
            'campaign_id' => $this->campaign->id,
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'meta']);

        $reportId = $response->json('data.id');

        $this->assertDatabaseHas('report_platform_sections', [
            'report_id' => $reportId,
            'platform_id' => $campaignPlatform->platform_id,
        ]);

        Event::assertDispatched(ReportCreated::class, fn (ReportCreated $event): bool => $event->reportId === $reportId);
    }

    public function test_update_report_allows_partial_data(): void
    {
        $report = $this->postJson('/api/reports', [
            'campaign_id' => $this->campaign->id,
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
        ])->json('data.id');

        $response = $this->patchJson("/api/reports/{$report}", [
            'status' => 'reviewed',
        ]);

        $response->assertStatus(200);
    }

    public function test_update_report_rejects_invalid_export_format(): void
    {
        $report = $this->postJson('/api/reports', [
            'campaign_id' => $this->campaign->id,
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
        ])->json('data.id');

        $response = $this->patchJson("/api/reports/{$report}", [
            'export_format' => 'docx',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['export_format']);
    }
}
