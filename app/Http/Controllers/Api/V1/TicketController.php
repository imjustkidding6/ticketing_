<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email', 'max:255'],
        ]);

        $tenant = $request->attributes->get('api_tenant');

        $client = Client::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => $validated['client_email'],
            ],
            [
                'name' => $validated['client_name'],
                'tier' => Client::TIER_BASIC,
                'status' => Client::STATUS_ACTIVE,
            ]
        );

        $defaultPriority = AppSetting::get('default_priority', 'medium');

        try {
            $ticket = $this->ticketService->createTicket([
                'tenant_id' => $tenant->id,
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'priority' => $defaultPriority,
                'client_id' => $client->id,
                'created_by' => null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Ticket created successfully.',
            'data' => $this->presentTicket($ticket->fresh(['client', 'department', 'category', 'assignee', 'products'])),
        ], 201);
    }

    public function show(Request $request, string $ticketNumber): JsonResponse
    {
        $ticket = Ticket::with(['client', 'department', 'category', 'assignee', 'products'])
            ->where('ticket_number', $ticketNumber)
            ->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        return response()->json([
            'data' => $this->presentTicket($ticket),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:open,assigned,in_progress,on_hold,closed,cancelled'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
            'client_email' => ['nullable', 'email'],
            'created_after' => ['nullable', 'date'],
            'created_before' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Ticket::with(['client', 'department', 'category', 'assignee', 'products'])
            ->notMerged()
            ->notSpam()
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('client_email')) {
            $clientIds = Client::where('email', $request->input('client_email'))->pluck('id');
            $query->whereIn('client_id', $clientIds);
        }

        if ($request->filled('created_after')) {
            $query->where('created_at', '>=', $request->input('created_after'));
        }

        if ($request->filled('created_before')) {
            $query->where('created_at', '<=', $request->input('created_before'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $tickets = $query->paginate($perPage);

        return response()->json([
            'data' => $tickets->getCollection()->map(fn ($t) => $this->presentTicket($t))->all(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    private function presentTicket(Ticket $ticket): array
    {
        return [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'tracking_token' => $ticket->tracking_token,
            'client' => [
                'name' => $ticket->client?->name,
                'email' => $ticket->client?->email,
            ],
            'department' => $ticket->department?->name,
            'category' => $ticket->category?->name,
            'products' => $ticket->relationLoaded('products')
                ? $ticket->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all()
                : [],
            'assigned_to' => $ticket->assignee?->name,
            'metadata' => $ticket->metadata,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
        ];
    }
}
