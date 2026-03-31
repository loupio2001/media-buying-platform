<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Client;
use App\Services\Api\ClientApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'industry' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $client = $this->clientApiService->store($data);

        return redirect()
            ->route('web.clients.show', $client->id)
            ->with('status', 'Client created successfully.');
    }

    public function show(int $client): View
    {
        $client = Client::query()
            ->with(['category', 'campaigns'])
            ->findOrFail($client);

        return view('clients.show', compact('client'));
    }
}
