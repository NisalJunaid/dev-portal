<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Software;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SoftwareController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Software::class);

        $query = Software::query()->with('client')->latest();
        $canManageSoftware = $request->user()->can('manage software');
        $clients = Client::query()->where('status', Client::STATUS_ACTIVE)->orderBy('name');

        if (! $canManageSoftware) {
            $query->where('client_id', $request->user()->client_id)->where('is_enabled', true);
            $clients->whereKey($request->user()->client_id);
        }

        return view('softwares.index', [
            'softwares' => $query->paginate(12),
            'clients' => $clients->get(),
            'canManageSoftware' => $canManageSoftware,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Software::class);

        return view('softwares.create', [
            'clients' => Client::query()->where('status', Client::STATUS_ACTIVE)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Software::class);

        $software = Software::create($this->validatedSoftware($request));

        return redirect()->route('softwares.index')->with('status', $software->name.' created successfully.');
    }

    public function edit(Software $software): View
    {
        $this->authorize('update', $software);

        return view('softwares.edit', [
            'software' => $software->load('client'),
            'clients' => Client::query()->where('status', Client::STATUS_ACTIVE)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Software $software): RedirectResponse
    {
        $this->authorize('update', $software);

        $software->update($this->validatedSoftware($request));

        return redirect()->route('softwares.index')->with('status', 'Software updated successfully.');
    }

    public function toggle(Software $software): RedirectResponse
    {
        $this->authorize('toggle', $software);

        $software->update(['is_enabled' => ! $software->is_enabled]);

        return back()->with('status', $software->name.' '.($software->is_enabled ? 'enabled' : 'disabled').' successfully.');
    }

    private function validatedSoftware(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('status', Client::STATUS_ACTIVE)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);
    }
}
