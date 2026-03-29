<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummaryService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardSummaryService $summaryService)
    {
    }

    public function __invoke(): View
    {
        return view('dashboard', [
            'summary' => $this->summaryService->summary(),
            'recentCampaigns' => $this->summaryService->recentCampaigns(),
        ]);
    }
}
