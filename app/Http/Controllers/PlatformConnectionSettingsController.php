<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\PlatformConnectionWebUpsertRequest;
use App\Models\Platform;
use App\Models\PlatformConnection;
use App\Services\Api\PlatformConnectionApiService;
use App\Services\Web\PlatformConnectionSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlatformConnectionSettingsController extends Controller
{
    public function __construct(
        private PlatformConnectionApiService $apiService,
        private PlatformConnectionSettingsService $settingsService,
    ) {
    }

    public function index(): View
    {
        $platforms = $this->settingsService->platformsWithConnections();

        return view('settings.platform-connections.index', [
            'platforms' => $platforms,
            'connectablePlatforms' => $platforms->where('api_supported', true)->values(),
            'healthLabels' => $platforms
                ->flatMap(fn (Platform $platform) => $platform->connections)
                ->mapWithKeys(fn (PlatformConnection $connection) => [
                    $connection->id => $this->settingsService->healthLabel($connection),
                ]),
        ]);
    }

    public function store(PlatformConnectionWebUpsertRequest $request): RedirectResponse
    {
        $this->apiService->store($request->validated(), (int) $request->user()->id);

        return redirect()
            ->route('web.platform-connections.index')
            ->with('status', 'Platform connection created successfully.');
    }

    public function update(PlatformConnectionWebUpsertRequest $request, PlatformConnection $platformConnection): RedirectResponse
    {
        $this->apiService->update($platformConnection, $request->validated());

        return redirect()
            ->route('web.platform-connections.index')
            ->with('status', 'Platform connection updated successfully.');
    }

    public function destroy(PlatformConnection $platformConnection): RedirectResponse
    {
        $this->apiService->delete($platformConnection);

        return redirect()
            ->route('web.platform-connections.index')
            ->with('status', 'Platform connection deleted successfully.');
    }
}