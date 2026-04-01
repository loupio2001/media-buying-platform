<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Report;
use App\Services\Api\ReportApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ReportWebController extends Controller
{
    public function __construct(private ReportApiService $reportApiService) {}

    public function index(): View
    {
        $reports = $this->reportApiService->index(15);

        return view('reports.index', compact('reports'));
    }

    public function create(): View
    {
        $campaigns = Campaign::query()->with('client:id,name')->orderBy('name')->get();

        return view('reports.create', compact('campaigns'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'type' => ['required', 'string', 'in:weekly,monthly,campaign'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $report = $this->reportApiService->store($data, (int) $request->user()->id);

        return redirect()
            ->route('web.reports.show', $report->id)
            ->with('status', 'Report created successfully.');
    }

    public function show(int $report): View
    {
        $report = Report::query()
            ->with(['campaign.client', 'platformSections.platform', 'creator'])
            ->findOrFail($report);

        return view('reports.show', compact('report'));
    }

    public function regenerateAiComments(Report $report): JsonResponse
    {
        try {
            $result = $this->reportApiService->regenerateAiComments($report);

            return response()->json([
                'data' => $result,
                'meta' => ['status' => 'regenerated'],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Failed to regenerate AI comments.',
                'error' => config('app.debug') ? $exception->getMessage() : null,
            ], 500);
        }
    }
}
