<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasSortableQuery;
use App\Models\Client;
use App\Models\ClientAgentAssignment;
use App\Models\Department;
use App\Models\Product;
use App\Models\SlaPolicy;
use App\Models\TicketCategory;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    use HasSortableQuery;

    /**
     * Display a listing of clients.
     */
    public function index(Request $request): View
    {
        $this->checkPermission('view clients');

        $query = Client::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->tier, fn ($query, $tier) => $query->where('tier', $tier));

        $this->applySort($query, $request, [
            'name' => 'name',
            'email' => 'email',
            'contact_person' => 'contact_person',
            'tier' => 'tier',
            'status' => 'status',
            'created_at' => 'created_at',
        ], 'created_at,desc');

        $clients = $query->paginate(15)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    /**
     * Show the form for creating a new client.
     */
    public function create(): View
    {
        $this->checkPermission('create clients');

        $slaPolicies = SlaPolicy::active()->get()->groupBy('client_tier');

        return view('clients.create', compact('slaPolicies'));
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->checkPermission('create clients');

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
        $this->checkPermission('view clients');

        $clientSlaPolicies = SlaPolicy::active()->where('client_tier', $client->tier)->get();
        $autofillUrl = $this->autofillSubmitUrl($client);

        // Data for the interactive autofill-link builder (only needed when the
        // tenant has a public portal, i.e. $autofillUrl is not null).
        $autofillData = $autofillUrl === null ? null : [
            'base' => route('tenant.submit-ticket', ['slug' => $client->tenant->slug]),
            'qrBase' => route('clients.autofill-qr', $client),
            'name' => $client->name,
            'email' => $client->email,
            'departments' => Department::active()->ordered()->get(['id', 'name']),
            'categories' => TicketCategory::active()->ordered()->get(['id', 'name', 'department_id']),
            'products' => Product::where('is_active', true)->ordered()->get(['id', 'name', 'category_id']),
        ];

        return view('clients.show', compact('client', 'clientSlaPolicies', 'autofillUrl', 'autofillData'));
    }

    /**
     * Stream a QR code (PNG) encoding the client's autofill submit-ticket link.
     * Optional department/category/products query params are baked into the link.
     * Pass ?download=1 to receive it as a file attachment.
     */
    public function autofillQr(Request $request, Client $client): Response
    {
        $this->checkPermission('view clients');

        $url = $this->autofillSubmitUrl($client, $this->autofillParamsFromRequest($request));
        abort_if($url === null, 404);

        $result = (new Builder(
            writer: new PngWriter,
            data: $url,
            size: 240,
            margin: 8,
        ))->build();

        $headers = [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'private, max-age=600',
        ];

        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="autofill-qr-'.$client->id.'.png"';
        }

        return response($result->getString(), 200, $headers);
    }

    /**
     * Build the public submit-ticket "autofill" deep link for a client, with the
     * client's name and email pre-filled (and locked on the form). Returns null
     * when the tenant has no public portal (Starter plan), where the link 404s.
     */
    private function autofillSubmitUrl(Client $client, array $params = []): ?string
    {
        $tenant = $client->tenant;
        $planSlug = $tenant?->plan()?->slug;

        if (! $tenant || $planSlug === null || $planSlug === 'start') {
            return null;
        }

        $query = array_merge([
            'name' => $client->name,
            'email' => $client->email,
        ], $params);

        return route('tenant.submit-ticket', ['slug' => $tenant->slug]).'?'.http_build_query($query);
    }

    /**
     * Pull the optional department/category/products selectors off the QR request.
     * Values are names (matched leniently by the submit form); products may be a
     * comma-separated string or an array.
     */
    private function autofillParamsFromRequest(Request $request): array
    {
        $params = [];

        foreach (['department', 'category'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && trim($value) !== '') {
                $params[$key] = trim($value);
            }
        }

        $products = $request->query('products');
        if (is_array($products)) {
            $products = array_values(array_filter(array_map('trim', $products), fn ($p) => $p !== ''));
            if ($products !== []) {
                $params['products'] = implode(',', $products);
            }
        } elseif (is_string($products) && trim($products) !== '') {
            $params['products'] = trim($products);
        }

        return $params;
    }

    /**
     * Show the form for editing the specified client.
     */
    public function edit(Client $client): View
    {
        $this->checkPermission('update clients');

        $slaPolicies = SlaPolicy::active()->get()->groupBy('client_tier');

        return view('clients.edit', compact('client', 'slaPolicies'));
    }

    /**
     * Update the specified client.
     */
    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->checkPermission('update clients');

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
        $this->checkPermission('delete clients');

        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }

    /**
     * Assign an agent to a client for the current month.
     */
    public function assignAgent(Request $request, Client $client): RedirectResponse
    {
        $this->checkPermission('update clients');

        $validated = $request->validate([
            'agent_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
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
