<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CampaignListController extends Controller
{
    public function __invoke(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $campaigns = Campaign::query()
            ->with('client:id,name')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('campaigns.index', [
            'campaigns' => $campaigns,
            'statusOptions' => CampaignStatus::cases(),
            'selectedStatus' => $status,
            'search' => $search,
        ]);
    }
}
