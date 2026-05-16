<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Client::class);

        return view('clients.index', [
            'clients' => Client::query()
                ->withCount(['softwares', 'users'])
                ->latest()
                ->paginate(12),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Client::class);

        return view('clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Client::class);

        $client = Client::create($this->validatedClient($request));

        return redirect()->route('clients.show', $client)->with('status', 'Client created successfully.');
    }

    public function show(Client $client): View
    {
        $this->authorize('view', $client);

        $canManageSoftware = auth()->user()->can('manage software');

        $client->load([
            'users.roles',
            'softwares' => fn ($query) => $query
                ->when(! $canManageSoftware, fn ($query) => $query->where('is_enabled', true))
                ->latest(),
        ])->loadCount([
            'users',
            'softwares' => fn ($query) => $query->when(! $canManageSoftware, fn ($query) => $query->where('is_enabled', true)),
        ]);

        return view('clients.show', [
            'client' => $client,
        ]);
    }

    public function edit(Client $client): View
    {
        $this->authorize('update', $client);

        return view('clients.edit', [
            'client' => $client,
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update($this->validatedClient($request));

        return redirect()->route('clients.show', $client)->with('status', 'Client updated successfully.');
    }

    public function disable(Client $client): RedirectResponse
    {
        $this->authorize('disable', $client);

        $client->update(['status' => Client::STATUS_INACTIVE]);

        return redirect()->route('clients.index')->with('status', 'Client disabled successfully.');
    }

    private function validatedClient(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in([Client::STATUS_ACTIVE, Client::STATUS_INACTIVE])],
        ]);
    }
}
