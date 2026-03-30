<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\Platform;
use App\Models\Report;
use App\Models\ReportPlatformSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPlatformSectionAiCommentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ReportPlatformSection $reportPlatformSection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::factory()->create(['created_by' => $this->admin->id]);
        $platform = Platform::factory()->create();

        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->reportPlatformSection = ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $platform->id,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_update_ai_comments_persists_report_platform_section_fields(): void
    {
        $payload = [
            'ai_summary' => 'Meta a surperformé sur le CTR mais sous-performe sur la portée.',
            'ai_highlights' => ['CTR en hausse de 18%', 'CPC sous benchmark'],
            'ai_concerns' => ['Reach limitée sur la semaine 2'],
            'ai_suggested_action' => 'Réallouer 15% du budget vers les ensembles les plus efficaces.',
            'performance_flags' => ['low_reach', 'strong_ctr'],
            'top_performing_ads' => ['Ad A', 'Ad B'],
            'worst_performing_ads' => ['Ad Z'],
            'human_notes' => 'A valider avec l equipe activation avant envoi client.',
        ];

        $response = $this->patchJson(route('report-platform-sections.ai-comments.update', $this->reportPlatformSection), $payload);

        $response->assertOk()
            ->assertJsonPath('meta.status', 'updated')
            ->assertJsonPath('data.id', $this->reportPlatformSection->id)
            ->assertJsonPath('data.ai_summary', $payload['ai_summary'])
            ->assertJsonPath('data.ai_suggested_action', $payload['ai_suggested_action'])
            ->assertJsonPath('data.human_notes', $payload['human_notes']);

        $section = $this->reportPlatformSection->refresh();

        $this->assertSame($payload['ai_summary'], $section->ai_summary);
        $this->assertSame($payload['ai_highlights'], $section->ai_highlights);
        $this->assertSame($payload['ai_concerns'], $section->ai_concerns);
        $this->assertSame($payload['ai_suggested_action'], $section->ai_suggested_action);
        $this->assertSame($payload['performance_flags'], $section->performance_flags);
        $this->assertSame($payload['top_performing_ads'], $section->top_performing_ads);
        $this->assertSame($payload['worst_performing_ads'], $section->worst_performing_ads);
        $this->assertSame($payload['human_notes'], $section->human_notes);
    }

    public function test_update_ai_comments_validates_required_and_array_fields(): void
    {
        $response = $this->patchJson(route('report-platform-sections.ai-comments.update', $this->reportPlatformSection), [
            'ai_highlights' => 'CTR en hausse',
            'ai_concerns' => ['Reach limitée'],
            'ai_suggested_action' => 'Réallouer du budget.',
            'performance_flags' => ['low_reach'],
            'top_performing_ads' => ['Ad A'],
            'worst_performing_ads' => ['Ad Z'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ai_summary', 'ai_highlights']);
    }
}