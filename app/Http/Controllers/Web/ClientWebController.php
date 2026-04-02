<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreClientWebRequest;
use App\Http\Requests\Web\UpdateClientWebRequest;
use App\Models\Category;
use App\Models\Client;
use App\Services\Api\ClientApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

class ClientWebController extends Controller
{
    public function __construct(private ClientApiService $clientApiService) {}

    public function index(): View
    {
        $clients = $this->clientApiService->index(15);

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        $categories = Category::query()->orderBy('name')->get();

        return view('clients.create', compact('categories'));
    }

    public function store(StoreClientWebRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $client = $this->clientApiService->store($data);

        return redirect()
            ->route('web.clients.show', $client->id)
            ->with('status', 'Client created successfully.');
    }

    public function edit(Client $client): View
    {
        $categories = Category::query()->orderBy('name')->get();

        return view('clients.edit', compact('client', 'categories'));
    }

    public function update(UpdateClientWebRequest $request, Client $client): RedirectResponse
    {
        $this->clientApiService->update($client, $request->validated());

        return redirect()
            ->route('web.clients.show', $client)
            ->with('status', 'Client updated successfully.');
    }

    public function show(int $client): View
    {
        $client = Client::query()
            ->with(['category', 'campaigns'])
            ->findOrFail($client);

        return view('clients.show', compact('client'));
    }

    public function destroy(Client $client): RedirectResponse
    {
        try {
            $this->clientApiService->delete($client);

            return redirect()
                ->route('web.clients.index')
                ->with('status', 'Client removed successfully.');
        } catch (QueryException) {
            return redirect()
                ->route('web.clients.show', $client)
                ->with('error', 'Client cannot be deleted because related records still exist.');
        }
    }
}
