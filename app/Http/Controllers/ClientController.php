<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAgentAssignment;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    /**
     * Display a listing of clients.
     */
    public function index(Request $request): View
    {
        $this->checkPermission('manage clients');

        $clients = Client::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->tier, fn ($query, $tier) => $query->where('tier', $tier))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    /**
     * Show the form for creating a new client.
     */
    public function create(): View
    {
        $slaPolicies = SlaPolicy::active()->get()->groupBy('client_tier');

        return view('clients.create', compact('slaPolicies'));
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'tier' => ['required', 'in:basic,premium,enterprise'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Client::create($validated);

        return redirect()->route('clients.index')
            ->with('success', 'Client created successfully.');
    }

    /**
     * Display the specified client.
     */
    public function show(Client $client): View
    {
        $clientSlaPolicies = SlaPolicy::active()->where('client_tier', $client->tier)->get();

        return view('clients.show', compact('client', 'clientSlaPolicies'));
    }

    /**
     * Show the form for editing the specified client.
     */
    public function edit(Client $client): View
    {
        $slaPolicies = SlaPolicy::active()->get()->groupBy('client_tier');

        return view('clients.edit', compact('client', 'slaPolicies'));
    }

    /**
     * Update the specified client.
     */
    public function update(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'tier' => ['required', 'in:basic,premium,enterprise'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $client->update($validated);

        return redirect()->route('clients.index')
            ->with('success', 'Client updated successfully.');
    }

    /**
     * Remove the specified client.
     */
    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }

    /**
     * Assign an agent to a client for the current month.
     */
    public function assignAgent(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $agent = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->findOrFail($validated['agent_id']);

        $assignmentMonth = now()->startOfMonth()->toDateString();

        // Deactivate existing assignments for this client this month
        ClientAgentAssignment::where('client_id', $client->id)
            ->where('assignment_month', $assignmentMonth)
            ->update(['is_active' => false]);

        ClientAgentAssignment::create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'assignment_month' => $assignmentMonth,
            'is_active' => true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('clients.show', $client)
            ->with('success', "Agent {$agent->name} assigned to this client.");
    }
}
