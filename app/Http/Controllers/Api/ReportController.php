<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ReportUpsertRequest;
use App\Http\Requests\Api\UpdateReportPlatformSectionAiCommentsRequest;
use App\Models\Report;
use App\Models\ReportPlatformSection;
use App\Services\Api\ReportApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends ApiController
{
    public function __construct(private ReportApiService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated($this->service->index((int) $request->integer('per_page', 15)));
    }

    public function store(ReportUpsertRequest $request): JsonResponse
    {
        $report = $this->service->store($request->validated(), (int) $request->user()->id);

        return $this->respond($report, ['status' => 'created'], 201);
    }

    public function show(Report $report): JsonResponse
    {
        $report->load(['campaign', 'creator', 'reviewer', 'platformSections']);

        return $this->respond($report, ['total' => 1]);
    }

    public function update(ReportUpsertRequest $request, Report $report): JsonResponse
    {
        return $this->respond($this->service->update($report, $request->validated()), ['status' => 'updated']);
    }

    public function updatePlatformSectionAiComments(
        UpdateReportPlatformSectionAiCommentsRequest $request,
        ReportPlatformSection $reportPlatformSection,
    ): JsonResponse {
        return $this->respond(
            $this->service->updatePlatformSectionAiComments($reportPlatformSection, $request->validated()),
            ['status' => 'updated']
        );
    }

    public function regenerateAiComments(Report $report): JsonResponse
    {
        return $this->respond(
            $this->service->regenerateAiComments($report),
            ['status' => 'regenerated']
        );
    }

    public function destroy(Report $report): JsonResponse
    {
        $this->service->delete($report);

        return $this->respond(['id' => $report->id], ['status' => 'deleted']);
    }
}
