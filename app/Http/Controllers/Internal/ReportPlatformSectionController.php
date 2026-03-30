<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\UpdateReportPlatformSectionAiCommentsRequest;
use App\Models\ReportPlatformSection;
use App\Services\Api\ReportApiService;
use Illuminate\Http\JsonResponse;

class ReportPlatformSectionController extends Controller
{
    public function __construct(private ReportApiService $service)
    {
    }

    public function updateAiComments(
        UpdateReportPlatformSectionAiCommentsRequest $request,
        ReportPlatformSection $reportPlatformSection,
    ): JsonResponse {
        $section = $this->service->updatePlatformSectionAiComments(
            $reportPlatformSection,
            $request->validated()
        );

        return response()->json([
            'data' => $section,
            'meta' => ['status' => 'updated'],
        ], 200);
    }

    public function showAiContext(ReportPlatformSection $reportPlatformSection): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getPlatformSectionAiContext($reportPlatformSection),
            'meta' => ['total' => 1],
        ], 200);
    }
}