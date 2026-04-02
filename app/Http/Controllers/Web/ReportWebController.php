<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreReportWebRequest;
use App\Http\Requests\Web\UpdateReportWebRequest;
use App\Models\Campaign;
use App\Models\Report;
use App\Services\Api\ReportApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
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

    public function store(StoreReportWebRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $report = $this->reportApiService->store($data, (int) $request->user()->id);

        return redirect()
            ->route('web.reports.show', $report->id)
            ->with('status', 'Report created successfully.');
    }

    public function edit(Report $report): View
    {
        $campaigns = Campaign::query()->with('client:id,name')->orderBy('name')->get();

        return view('reports.edit', compact('report', 'campaigns'));
    }

    public function update(UpdateReportWebRequest $request, Report $report): RedirectResponse
    {
        $this->reportApiService->update($report, $request->validated());

        return redirect()
            ->route('web.reports.show', $report)
            ->with('status', 'Report updated successfully.');
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
                'error' => config('app.debug') ? $this->sanitizeErrorForJson($exception->getMessage()) : null,
            ], 500);
        }
    }

    public function destroy(Report $report): RedirectResponse
    {
        try {
            $this->reportApiService->delete($report);

            return redirect()
                ->route('web.reports.index')
                ->with('status', 'Report removed successfully.');
        } catch (QueryException) {
            return redirect()
                ->route('web.reports.show', $report)
                ->with('error', 'Report cannot be deleted because related records still exist.');
        }
    }

    private function sanitizeErrorForJson(string $message): string
    {
        $clean = preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $message) ?? $message;
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);

        if (function_exists('mb_convert_encoding')) {
            $clean = mb_convert_encoding($clean, 'UTF-8', 'UTF-8');
        }

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $clean);
            if ($converted !== false) {
                $clean = $converted;
            }
        }

        return trim($clean);
    }
}
