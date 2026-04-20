<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Apply common filters to a ticket query.
     *
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        // Merged source tickets are archival artifacts (their counts, closed_at,
        // and resolution metrics would otherwise double-count with their target).
        $query->notMerged();

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'open') {
                $query->open();
            } elseif ($filters['status'] === 'closed') {
                $query->closed();
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas('products', fn ($q) => $q->where('products.id', $filters['product_id']));
        }

        if (! empty($filters['restrict_department_ids'])) {
            $query->whereIn('department_id', $filters['restrict_department_ids']);
        }

        return $query;
    }

    /**
     * Get ticket volume report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTicketVolumeReport(array $filters = []): array
    {
        $query = $this->applyFilters(Ticket::query(), $filters);

        return [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->whereIn('status', ['open', 'assigned', 'in_progress', 'on_hold'])->count(),
            'closed' => (clone $query)->whereIn('status', ['closed', 'cancelled'])->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'on_hold' => (clone $query)->where('status', 'on_hold')->count(),
            'by_priority' => [
                'critical' => (clone $query)->where('priority', 'critical')->count(),
                'high' => (clone $query)->where('priority', 'high')->count(),
                'medium' => (clone $query)->where('priority', 'medium')->count(),
                'low' => (clone $query)->where('priority', 'low')->count(),
            ],
            'by_status' => [
                'open' => (clone $query)->where('status', 'open')->count(),
                'assigned' => (clone $query)->where('status', 'assigned')->count(),
                'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
                'on_hold' => (clone $query)->where('status', 'on_hold')->count(),
                'closed' => (clone $query)->where('status', 'closed')->count(),
                'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            ],
        ];
    }

    /**
     * Get individual ticket report with resolution times.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getTicketReport(array $filters = []): Collection
    {
        $query = $this->applyFilters(
            Ticket::query()->with(['client', 'department', 'category', 'assignee']),
            $filters
        );

        return $query->latest()->limit(500)->get()->map(fn (Ticket $t) => [
            'ticket_number' => $t->ticket_number,
            'subject' => $t->subject,
            'client' => $t->client?->name ?? '-',
            'department' => $t->department?->name ?? '-',
            'category' => $t->category?->name ?? '-',
            'priority' => $t->priority,
            'status' => $t->status,
            'assigned_to' => $t->assignee?->name ?? '-',
            'created_at' => $t->created_at->format('m/d/Y g:i A'),
            'in_progress_at' => $t->in_progress_at?->format('m/d/Y g:i A') ?? '-',
            'closed_at' => $t->closed_at?->format('m/d/Y g:i A') ?? '-',
            'resolution_hours' => $t->getEffectiveResolutionTimeHours(),
            'work_hours' => $t->getWorkResolutionTimeHours(),
            'resolution_formatted' => Ticket::formatHours($t->getEffectiveResolutionTimeHours()),
            'work_formatted' => Ticket::formatHours($t->getWorkResolutionTimeHours()),
        ]);
    }

    /**
     * Get resolution report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getResolutionReport(array $filters = []): array
    {
        $query = Ticket::query()
            ->where('status', 'closed')
            ->whereNotNull('closed_at');

        if (! empty($filters['from'])) {
            $query->where('closed_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('closed_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas('products', fn ($q) => $q->where('products.id', $filters['product_id']));
        }

        $closedTickets = $query->get();

        $avgResolutionHours = $closedTickets->avg(function ($ticket) {
            return $ticket->getEffectiveResolutionTimeHours();
        });

        $ticketsWithWorkTime = $closedTickets->filter(fn ($t) => $t->in_progress_at !== null);
        $avgWorkHours = $ticketsWithWorkTime->avg(function ($ticket) {
            return $ticket->getWorkResolutionTimeHours();
        });

        return [
            'total_closed' => $closedTickets->count(),
            'avg_resolution_hours' => round($avgResolutionHours ?? 0, 1),
            'avg_work_hours' => round($avgWorkHours ?? 0, 1),
        ];
    }

    /**
     * Get department report.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getDepartmentReport(array $filters = []): Collection
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30);
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now();

        $ticketFilter = function ($q) use ($filters, $from, $to) {
            $q->whereBetween('created_at', [$from, $to]);

            if (! empty($filters['priority'])) {
                $q->where('priority', $filters['priority']);
            }

            if (! empty($filters['client_id'])) {
                $q->where('client_id', $filters['client_id']);
            }

            if (! empty($filters['assigned_to'])) {
                $q->where('assigned_to', $filters['assigned_to']);
            }

            if (! empty($filters['category_id'])) {
                $q->where('category_id', $filters['category_id']);
            }

            if (! empty($filters['product_id'])) {
                $q->whereHas('products', fn ($pq) => $pq->where('products.id', $filters['product_id']));
            }
        };

        return Department::query()
            ->withCount([
                'tickets as total_tickets' => fn ($q) => $ticketFilter($q),
                'tickets as open_tickets' => fn ($q) => $ticketFilter($q->open()),
                'tickets as closed_tickets' => fn ($q) => $ticketFilter($q->closed()),
            ])
            ->get()
            ->map(fn ($dept) => [
                'name' => $dept->name,
                'total' => $dept->total_tickets,
                'open' => $dept->open_tickets,
                'closed' => $dept->closed_tickets,
            ]);
    }

    /**
     * Get agent performance report.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getAgentPerformanceReport(array $filters = []): Collection
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30);
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now();

        $ticketFilter = function ($q) use ($filters, $from, $to) {
            $q->whereBetween('tickets.created_at', [$from, $to])
                ->where('tickets.is_merged', false);

            if (! empty($filters['priority'])) {
                $q->where('tickets.priority', $filters['priority']);
            }

            if (! empty($filters['department_id'])) {
                $q->where('tickets.department_id', $filters['department_id']);
            }

            if (! empty($filters['category_id'])) {
                $q->where('tickets.category_id', $filters['category_id']);
            }

            if (! empty($filters['client_id'])) {
                $q->where('tickets.client_id', $filters['client_id']);
            }
        };

        return User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->whereHas('tickets', fn ($q) => $ticketFilter($q))
            ->withCount([
                'tickets as total_tickets' => fn ($q) => $ticketFilter($q),
                'tickets as open_tickets' => fn ($q) => $ticketFilter($q->open()),
                'tickets as closed_tickets' => fn ($q) => $ticketFilter($q->where('tickets.status', 'closed')),
            ])
            ->get()
            ->map(function ($agent) use ($from, $to, $filters) {
                $closedQuery = Ticket::where('assigned_to', $agent->id)
                    ->notMerged()
                    ->where('status', 'closed')
                    ->whereNotNull('closed_at')
                    ->whereBetween('closed_at', [$from, $to]);

                if (! empty($filters['department_id'])) {
                    $closedQuery->where('department_id', $filters['department_id']);
                }

                if (! empty($filters['category_id'])) {
                    $closedQuery->where('category_id', $filters['category_id']);
                }

                $closedTickets = $closedQuery->get();
                $avgHours = $closedTickets->avg(fn ($t) => $t->created_at->diffInHours($t->closed_at));

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'total' => $agent->total_tickets,
                    'open' => $agent->open_tickets,
                    'closed' => $agent->closed_tickets,
                    'avg_resolution_hours' => round($avgHours ?? 0, 1),
                ];
            });
    }

    /**
     * Get category report.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getCategoryReport(array $filters = []): Collection
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30);
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now();

        $ticketFilter = function ($q) use ($filters, $from, $to) {
            $q->whereBetween('created_at', [$from, $to]);

            if (! empty($filters['priority'])) {
                $q->where('priority', $filters['priority']);
            }

            if (! empty($filters['department_id'])) {
                $q->where('department_id', $filters['department_id']);
            }

            if (! empty($filters['client_id'])) {
                $q->where('client_id', $filters['client_id']);
            }

            if (! empty($filters['assigned_to'])) {
                $q->where('assigned_to', $filters['assigned_to']);
            }
        };

        return \App\Models\TicketCategory::query()
            ->with('department')
            ->withCount([
                'tickets as total_tickets' => fn ($q) => $ticketFilter($q),
                'tickets as open_tickets' => fn ($q) => $ticketFilter($q->open()),
                'tickets as closed_tickets' => fn ($q) => $ticketFilter($q->closed()),
            ])
            ->get()
            ->map(fn ($cat) => [
                'name' => $cat->name,
                'department' => $cat->department?->name ?? '-',
                'total' => $cat->total_tickets,
                'open' => $cat->open_tickets,
                'closed' => $cat->closed_tickets,
            ]);
    }

    /**
     * Get client report.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getClientReport(array $filters = []): Collection
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30);
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now();

        $ticketFilter = function ($q) use ($filters, $from, $to) {
            $q->whereBetween('created_at', [$from, $to]);

            if (! empty($filters['priority'])) {
                $q->where('priority', $filters['priority']);
            }

            if (! empty($filters['department_id'])) {
                $q->where('department_id', $filters['department_id']);
            }

            if (! empty($filters['category_id'])) {
                $q->where('category_id', $filters['category_id']);
            }

            if (! empty($filters['assigned_to'])) {
                $q->where('assigned_to', $filters['assigned_to']);
            }
        };

        return Client::query()
            ->withCount([
                'tickets as total_tickets' => fn ($q) => $ticketFilter($q),
                'tickets as open_tickets' => fn ($q) => $ticketFilter($q->open()),
                'tickets as closed_tickets' => fn ($q) => $ticketFilter($q->closed()),
            ])
            ->get()
            ->map(fn ($client) => [
                'name' => $client->name,
                'email' => $client->email,
                'total' => $client->total_tickets,
                'open' => $client->open_tickets,
                'closed' => $client->closed_tickets,
            ]);
    }

    /**
     * Get product report.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getProductReport(array $filters = []): Collection
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30);
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now();

        $ticketFilter = function ($q) use ($filters, $from, $to) {
            $q->whereBetween('tickets.created_at', [$from, $to])
                ->where('tickets.is_merged', false);

            if (! empty($filters['priority'])) {
                $q->where('tickets.priority', $filters['priority']);
            }

            if (! empty($filters['department_id'])) {
                $q->where('tickets.department_id', $filters['department_id']);
            }

            if (! empty($filters['category_id'])) {
                $q->where('tickets.category_id', $filters['category_id']);
            }

            if (! empty($filters['client_id'])) {
                $q->where('tickets.client_id', $filters['client_id']);
            }

            if (! empty($filters['assigned_to'])) {
                $q->where('tickets.assigned_to', $filters['assigned_to']);
            }
        };

        return \App\Models\Product::query()
            ->withCount([
                'tickets as total_tickets' => fn ($q) => $ticketFilter($q),
                'tickets as open_tickets' => fn ($q) => $ticketFilter($q->whereIn('tickets.status', ['open', 'assigned', 'in_progress', 'on_hold'])),
                'tickets as closed_tickets' => fn ($q) => $ticketFilter($q->whereIn('tickets.status', ['closed', 'cancelled'])),
            ])
            ->get()
            ->map(fn ($product) => [
                'name' => $product->name,
                'total' => $product->total_tickets,
                'open' => $product->open_tickets,
                'closed' => $product->closed_tickets,
            ]);
    }

    /**
     * Get daily ticket trend data.
     *
     * @param  array<string, mixed>  $filters
     * @param  string  $groupBy  daily|weekly|monthly|yearly
     * @return array<int, array{label: string, count: int}>
     */
    public function getTrend(array $filters = [], string $groupBy = 'daily'): array
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $query = $this->applyFilters(Ticket::query(), $filters);

        $selectExpr = match ($groupBy) {
            'weekly' => 'YEARWEEK(created_at, 1) as period, MIN(DATE(created_at)) as period_start',
            'monthly' => "DATE_FORMAT(created_at, '%Y-%m') as period, DATE_FORMAT(created_at, '%Y-%m') as period_start",
            'yearly' => 'YEAR(created_at) as period, YEAR(created_at) as period_start',
            default => 'DATE(created_at) as period, DATE(created_at) as period_start',
        };

        $groupExpr = match ($groupBy) {
            'weekly' => 'YEARWEEK(created_at, 1)',
            'monthly' => "DATE_FORMAT(created_at, '%Y-%m')",
            'yearly' => 'YEAR(created_at)',
            default => 'DATE(created_at)',
        };

        $results = (clone $query)
            ->selectRaw("{$selectExpr}, COUNT(*) as count")
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw($groupExpr)
            ->orderByRaw('MIN(created_at)')
            ->get()
            ->keyBy('period');

        $trend = [];

        if ($groupBy === 'daily') {
            $current = $from->copy();
            while ($current->lte($to)) {
                $key = $current->toDateString();
                $trend[] = ['label' => $key, 'count' => (int) ($results[$key]->count ?? 0)];
                $current->addDay();
            }
        } elseif ($groupBy === 'weekly') {
            $current = $from->copy()->startOfWeek();
            while ($current->lte($to)) {
                $key = $current->format('oW');
                $label = 'W'.$current->isoFormat('W').' '.$current->format('M/d');
                $trend[] = ['label' => $label, 'count' => (int) ($results[$key]->count ?? 0)];
                $current->addWeek();
            }
        } elseif ($groupBy === 'monthly') {
            $current = $from->copy()->startOfMonth();
            while ($current->lte($to)) {
                $key = $current->format('Y-m');
                $trend[] = ['label' => $current->format('M Y'), 'count' => (int) ($results[$key]->count ?? 0)];
                $current->addMonth();
            }
        } elseif ($groupBy === 'yearly') {
            $current = $from->copy()->startOfYear();
            while ($current->lte($to)) {
                $key = (string) $current->year;
                $trend[] = ['label' => $key, 'count' => (int) ($results[$key]->count ?? 0)];
                $current->addYear();
            }
        }

        return $trend;
    }

    /**
     * Get top N items for a given grouping.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getTopDepartments(array $filters = [], int $limit = 5): Collection
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30);
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now();

        return Department::query()
            ->withCount(['tickets as total' => function ($q) use ($filters, $from, $to) {
                $q->whereBetween('created_at', [$from, $to]);

                if (! empty($filters['priority'])) {
                    $q->where('priority', $filters['priority']);
                }

                if (! empty($filters['client_id'])) {
                    $q->where('client_id', $filters['client_id']);
                }

                if (! empty($filters['assigned_to'])) {
                    $q->where('assigned_to', $filters['assigned_to']);
                }
            }])
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($d) => ['name' => $d->name, 'total' => $d->total]);
    }

    /**
     * Get top N clients by ticket count.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getTopClients(array $filters = [], int $limit = 5): Collection
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30);
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now();

        return Client::query()
            ->withCount(['tickets as total' => function ($q) use ($filters, $from, $to) {
                $q->whereBetween('created_at', [$from, $to]);

                if (! empty($filters['priority'])) {
                    $q->where('priority', $filters['priority']);
                }

                if (! empty($filters['department_id'])) {
                    $q->where('department_id', $filters['department_id']);
                }

                if (! empty($filters['assigned_to'])) {
                    $q->where('assigned_to', $filters['assigned_to']);
                }
            }])
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => ['name' => $c->name, 'total' => $c->total]);
    }

    /**
     * Get top N agents by ticket count.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getTopAgents(array $filters = [], int $limit = 5): Collection
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30);
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now();

        return User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->withCount(['tickets as total' => function ($q) use ($filters, $from, $to) {
                $q->whereBetween('tickets.created_at', [$from, $to]);

                if (! empty($filters['priority'])) {
                    $q->where('tickets.priority', $filters['priority']);
                }

                if (! empty($filters['department_id'])) {
                    $q->where('tickets.department_id', $filters['department_id']);
                }

                if (! empty($filters['client_id'])) {
                    $q->where('tickets.client_id', $filters['client_id']);
                }
            }])
            ->having('total', '>', 0)
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($a) => ['name' => $a->name, 'total' => $a->total]);
    }

    /**
     * Get billing report.
     *
     * @param  array<string, mixed>  $filters
     * @return array{tickets: Collection, summary: array<string, mixed>}
     */
    public function getBillingReport(array $filters = []): array
    {
        $query = $this->applyFilters(
            Ticket::query()->with(['client', 'department', 'assignee'])->where('is_billable', true),
            $filters
        );

        if (! empty($filters['billing_status'])) {
            if ($filters['billing_status'] === 'billed') {
                $query->whereNotNull('billed_at');
            } elseif ($filters['billing_status'] === 'unbilled') {
                $query->whereNull('billed_at');
            }
        }

        $tickets = $query->latest()->limit(500)->get();

        $summary = [
            'total_billable' => $tickets->count(),
            'total_billed' => $tickets->whereNotNull('billed_at')->count(),
            'total_unbilled' => $tickets->whereNull('billed_at')->count(),
            'total_amount' => $tickets->sum('billable_amount'),
            'billed_amount' => $tickets->whereNotNull('billed_at')->sum('billable_amount'),
            'unbilled_amount' => $tickets->whereNull('billed_at')->sum('billable_amount'),
        ];

        return compact('tickets', 'summary');
    }

    /**
     * Get multi-series trend grouped by an entity (department, category, client, agent, product).
     *
     * @param  array<string, mixed>  $filters
     * @param  string  $groupBy  daily|weekly|monthly|yearly
     * @param  string  $entity  department|category|client|agent|product
     * @return array{labels: list<string>, series: list<array{name: string, data: list<int>}>}
     */
    public function getEntityTrend(array $filters = [], string $groupBy = 'daily', string $entity = 'department', int $limit = 5): array
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $entityConfig = match ($entity) {
            'department' => ['column' => 'department_id', 'model' => Department::class, 'nameField' => 'name'],
            'category' => ['column' => 'category_id', 'model' => \App\Models\TicketCategory::class, 'nameField' => 'name'],
            'client' => ['column' => 'client_id', 'model' => Client::class, 'nameField' => 'name'],
            'agent' => ['column' => 'assigned_to', 'model' => User::class, 'nameField' => 'name'],
            'product' => ['column' => null, 'model' => \App\Models\Product::class, 'nameField' => 'name'],
            default => ['column' => 'department_id', 'model' => Department::class, 'nameField' => 'name'],
        };

        // Get top N entities by ticket count
        $baseQuery = $this->applyFilters(Ticket::query(), $filters)
            ->whereBetween('created_at', [$from, $to]);

        if ($entity === 'product') {
            $topIds = \App\Models\Product::query()
                ->withCount(['tickets as tc' => fn ($q) => $q->whereBetween('tickets.created_at', [$from, $to])])
                ->orderByDesc('tc')
                ->limit($limit)
                ->pluck('id', 'name');
        } else {
            $topIds = (clone $baseQuery)
                ->selectRaw($entityConfig['column'].',  COUNT(*) as tc')
                ->whereNotNull($entityConfig['column'])
                ->groupBy($entityConfig['column'])
                ->orderByDesc('tc')
                ->limit($limit)
                ->pluck('tc', $entityConfig['column']);

            $entityNames = $entityConfig['model']::whereIn('id', $topIds->keys())->pluck($entityConfig['nameField'], 'id');
        }

        // Build period labels
        $periodFormat = match ($groupBy) {
            'weekly' => 'oW',
            'monthly' => 'Y-m',
            'yearly' => 'Y',
            default => 'Y-m-d',
        };

        $labels = [];
        $current = match ($groupBy) {
            'weekly' => $from->copy()->startOfWeek(),
            'monthly' => $from->copy()->startOfMonth(),
            'yearly' => $from->copy()->startOfYear(),
            default => $from->copy(),
        };

        while ($current->lte($to)) {
            $labels[] = match ($groupBy) {
                'weekly' => 'W'.$current->isoFormat('W').' '.$current->format('M/d'),
                'monthly' => $current->format('M Y'),
                'yearly' => (string) $current->year,
                default => $current->toDateString(),
            };
            $current = match ($groupBy) {
                'weekly' => $current->addWeek(),
                'monthly' => $current->addMonth(),
                'yearly' => $current->addYear(),
                default => $current->addDay(),
            };
        }

        $groupExpr = match ($groupBy) {
            'weekly' => 'YEARWEEK(created_at, 1)',
            'monthly' => "DATE_FORMAT(created_at, '%Y-%m')",
            'yearly' => 'YEAR(created_at)',
            default => 'DATE(created_at)',
        };

        $series = [];

        if ($entity === 'product') {
            foreach ($topIds as $name => $productId) {
                $counts = Ticket::query()
                    ->notMerged()
                    ->whereHas('products', fn ($q) => $q->where('products.id', $productId))
                    ->whereBetween('created_at', [$from, $to])
                    ->selectRaw("{$groupExpr} as period, COUNT(*) as cnt")
                    ->groupByRaw($groupExpr)
                    ->pluck('cnt', 'period');

                $data = [];
                $c = match ($groupBy) {
                    'weekly' => $from->copy()->startOfWeek(),
                    'monthly' => $from->copy()->startOfMonth(),
                    'yearly' => $from->copy()->startOfYear(),
                    default => $from->copy(),
                };
                while ($c->lte($to)) {
                    $key = $c->format($periodFormat);
                    $data[] = (int) ($counts[$key] ?? 0);
                    $c = match ($groupBy) {
                        'weekly' => $c->addWeek(),
                        'monthly' => $c->addMonth(),
                        'yearly' => $c->addYear(),
                        default => $c->addDay(),
                    };
                }

                $series[] = ['name' => $name, 'data' => $data];
            }
        } else {
            foreach ($topIds->keys() as $entityId) {
                $name = $entityNames[$entityId] ?? 'Unknown';

                $counts = (clone $baseQuery)
                    ->where($entityConfig['column'], $entityId)
                    ->selectRaw("{$groupExpr} as period, COUNT(*) as cnt")
                    ->groupByRaw($groupExpr)
                    ->pluck('cnt', 'period');

                $data = [];
                $c = match ($groupBy) {
                    'weekly' => $from->copy()->startOfWeek(),
                    'monthly' => $from->copy()->startOfMonth(),
                    'yearly' => $from->copy()->startOfYear(),
                    default => $from->copy(),
                };
                while ($c->lte($to)) {
                    $key = $c->format($periodFormat);
                    $data[] = (int) ($counts[$key] ?? 0);
                    $c = match ($groupBy) {
                        'weekly' => $c->addWeek(),
                        'monthly' => $c->addMonth(),
                        'yearly' => $c->addYear(),
                        default => $c->addDay(),
                    };
                }

                $series[] = ['name' => $name, 'data' => $data];
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * Export data as CSV.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @param  list<string>  $headers
     */
    public function exportToCsv(array $data, array $headers, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($data as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
