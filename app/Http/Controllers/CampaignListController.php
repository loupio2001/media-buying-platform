<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CampaignListController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'q' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $campaigns = Campaign::query()
            ->with('client:id,name')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->when($startDate, fn ($query) => $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay()))
            ->when($endDate, fn ($query) => $query->where('created_at', '<', Carbon::createFromFormat('Y-m-d', $endDate)->addDay()->startOfDay()))
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('campaigns.index', [
            'campaigns' => $campaigns,
            'statusOptions' => CampaignStatus::cases(),
            'selectedStatus' => $status,
            'search' => $search,
            'startDate' => $startDate ?? '',
            'endDate' => $endDate ?? '',
        ]);
    }
}
