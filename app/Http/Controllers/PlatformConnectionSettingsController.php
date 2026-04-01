<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\PlatformConnectionWebUpsertRequest;
use App\Models\Platform;
use App\Models\PlatformConnection;
use App\Services\Api\PlatformConnectionApiService;
use App\Services\Web\PlatformManualSyncRunner;
use App\Services\Web\PlatformConnectionSettingsService;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Str;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Throwable;

class PlatformConnectionSettingsController extends Controller
{
    public function __construct(
        private PlatformConnectionApiService $apiService,
        private PlatformConnectionSettingsService $settingsService,
        private PlatformManualSyncRunner $syncRunner,
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

    public function syncAll(): RedirectResponse
    {
        $redirectTo = url()->previous() ?: route('web.platform-connections.index', [], false);

        try {
            $this->syncRunner->dispatchAll();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->to($redirectTo)
                ->with('error', $this->syncErrorMessage($exception));
        }

        return redirect()
            ->to($redirectTo)
            ->with('status', 'Manual sync has been dispatched for all active platform campaigns.');
    }

    public function syncConnection(PlatformConnection $platformConnection): RedirectResponse
    {
        $redirectTo = url()->previous() ?: route('web.platform-connections.index', [], false);

        try {
            $this->syncRunner->dispatchConnection((int) $platformConnection->id);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->to($redirectTo)
                ->with('error', $this->syncErrorMessage($exception));
        }

        return redirect()
            ->to($redirectTo)
            ->with('status', 'Manual sync has been dispatched for the selected platform connection.');
    }

    private function syncErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof ProcessFailedException) {
            $stderr = trim((string) $exception->result->errorOutput());
            if ($stderr !== '') {
                $summary = Str::of($stderr)
                    ->replaceMatches('/\s+/', ' ')
                    ->limit(220, '...')
                    ->toString();

                return 'Manual sync dispatch failed: ' . $summary;
            }

            return 'Manual sync dispatch failed. Check that Redis is running and collectors are configured (DB_USER/DB_NAME, REDIS_URL, PYTHON_BIN).';
        }

        return 'Manual sync dispatch failed: ' . mb_substr($exception->getMessage(), 0, 300);
    }
}