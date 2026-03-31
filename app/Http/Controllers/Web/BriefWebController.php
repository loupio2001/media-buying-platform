<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Brief;
use App\Models\Campaign;
use App\Services\Api\BriefApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BriefWebController extends Controller
{
    public function __construct(private BriefApiService $briefApiService) {}

    public function index(): View
    {
        $briefs = $this->briefApiService->index(15);

        return view('briefs.index', compact('briefs'));
    }

    public function create(): View
    {
        $campaigns = Campaign::query()->with('client:id,name')->orderBy('name')->get();

        return view('briefs.create', compact('campaigns'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'objective' => ['nullable', 'string', 'max:1000'],
            'budget_total' => ['nullable', 'numeric', 'min:0'],
            'flight_start' => ['nullable', 'date'],
            'flight_end' => ['nullable', 'date', 'after_or_equal:flight_start'],
            'target_audience' => ['nullable', 'string', 'max:1000'],
        ]);

        $brief = $this->briefApiService->store($data);

        return redirect()
            ->route('web.briefs.show', $brief->id)
            ->with('status', 'Brief created successfully.');
    }

    public function show(int $brief): View
    {
        $brief = Brief::query()
            ->with(['campaign.client'])
            ->findOrFail($brief);

        return view('briefs.show', compact('brief'));
    }
}
