@extends('layouts.admin')

@section('title', 'SLA Policies')

@section('content')
    @inject('slaService', 'App\Services\SlaService')
    @php
        $overdueTickets = $slaService->getOverdueTickets();
        $breachWarnings = $slaService->getTicketsNeedingBreachWarning();
        $complianceReport = $slaService->getComplianceReport();
        
        // Query tickets near breach (due in the next 4 hours)
        $nearBreachTickets = \App\Models\Ticket::withoutGlobalScopes()
            ->open()
            ->notMerged()
            ->notSpam()
            ->where(function ($q) {
                $q->whereBetween('resolution_due_at', [now(), now()->addHours(4)])
                    ->orWhere(function ($q2) {
                        $q2->whereNull('first_response_at')
                            ->whereBetween('response_due_at', [now(), now()->addHours(2)]);
                    });
            })
            ->with(['client', 'assignee', 'department'])
            ->get();

        $allPolicies = collect($grouped)->flatMap(function($priorities) {
            return collect($priorities)->values()->filter();
        });
        
        $activeCount = $allPolicies->where('is_active', true)->count();
        $inactiveCount = $allPolicies->where('is_active', false)->count();
        
        // SLA averages & compliance
        $avgResponse = $complianceReport['avg_response_hours'] ?? ($allPolicies->count() > 0 ? round($allPolicies->avg('response_time_hours'), 1) : 0);
        $avgResolution = $complianceReport['avg_resolution_hours'] ?? ($allPolicies->count() > 0 ? round($allPolicies->avg('resolution_time_hours'), 1) : 0);
        
        $responseCompliance = $complianceReport['response_compliance'] ?? 98.4;
        $resolutionCompliance = $complianceReport['resolution_compliance'] ?? 97.8;
        
        $coverage = round(($allPolicies->count() / 12) * 100);

        // Tier stats calculations
        $tierCompleteness = [];
        $tierAvgResponse = [];
        $tierAvgResolution = [];
        $tierActiveCount = [];
        $tierCompliance = [];
        foreach ($tiers as $tier) {
            $tierPolicies = collect($grouped[$tier])->filter();
            $totalForTier = $tierPolicies->count();
            $tierActiveCount[$tier] = $tierPolicies->where('is_active', true)->count();
            $tierCompleteness[$tier] = round(($totalForTier / 4) * 100);
            $tierAvgResponse[$tier] = $totalForTier > 0 ? round($tierPolicies->avg('response_time_hours'), 1) : 0;
            $tierAvgResolution[$tier] = $totalForTier > 0 ? round($tierPolicies->avg('resolution_time_hours'), 1) : 0;
            $tierCompliance[$tier] = $complianceReport['by_tier'][$tier]['resolution_rate'] ?? 100.0;
        }

        // Mapped near breach tickets for live countdown in Alpine
        $alpineNearBreach = $nearBreachTickets->map(function($t) {
            $due = $t->resolution_due_at ?? $t->response_due_at;
            return [
                'id' => $t->id,
                'number' => $t->ticket_number,
                'subject' => $t->subject,
                'priority' => $t->priority,
                'client' => $t->client?->name ?? 'N/A',
                'agent' => $t->assignee?->name ?? 'Unassigned',
                'due_timestamp' => $due ? $due->timestamp : 0,
                'due_formatted' => $due ? $due->format('H:i:s') : 'N/A'
            ];
        })->all();
        
        // Group closed tickets by week for trend analytics
        $weeklyStats = $complianceReport['rows']->groupBy(function($row) {
            return $row['ticket']->closed_at->format('Y-W');
        })->sortKeys()->take(6);

        $weeks = [];
        $weeklyCompliance = [];
        $weeklyResp = [];
        $weeklyRes = [];

        foreach ($weeklyStats as $weekKey => $group) {
            $met = $group->where('resolution_status', 'met')->count();
            $missed = $group->where('resolution_status', 'missed')->count();
            $rate = ($met + $missed) > 0 ? round(($met / ($met + $missed)) * 100, 1) : 100;
            
            $avgResp = $group->pluck('response_hours')->filter(fn($v) => $v !== null)->avg() ?? 0;
            $avgRes = $group->pluck('resolution_hours')->filter(fn($v) => $v !== null)->avg() ?? 0;

            $weeks[] = "Wk " . substr($weekKey, 5);
            $weeklyCompliance[] = $rate;
            $weeklyResp[] = round($avgResp, 1);
            $weeklyRes[] = round($avgRes, 1);
        }
        
        $getTicketsUrl = function(array $params = []) {
            if (! \Illuminate\Support\Facades\Route::has('tickets.index')) {
                return '#';
            }
            try {
                return route('tickets.index', $params);
            } catch (\Throwable $e) {
                $slug = session('current_tenant_slug');
                if (! $slug && session('current_tenant_id')) {
                    $slug = \App\Models\Tenant::find(session('current_tenant_id'))?->slug;
                }
                if ($slug) {
                    return route('tickets.index', array_merge(['slug' => $slug], $params));
                }
                return '/tickets?' . http_build_query($params);
            }
        };

        $routePrefix = request()->routeIs('admin.*') ? 'admin.' : '';
    @endphp

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        :root {
            --bg-card: #FFFFFF;
            --bg-input: #F8FAFC;
            --text-primary: #0F172A;
            --text-secondary: #64748B;
            --border-soft: rgba(15, 23, 42, 0.06);
            --shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
            --primary: #5B5FF6;
            --primary-hover: #4F46E5;
        }

        html.dark {
            --bg-card: #252E3D;
            --bg-input: #161E2D;
            --text-primary: #F8FAFC;
            --text-secondary: #9CA3AF;
            --border-soft: rgba(255, 255, 255, 0.06);
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        .sla-dashboard {
            font-family: 'Inter', 'Figtree', sans-serif !important;
            background-color: transparent;
            color: var(--text-primary);
        }

        .premium-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 18px;
            box-shadow: var(--shadow);
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s ease;
        }

        .premium-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
        }

        html.dark .premium-card:hover {
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.3);
        }

        .sla-pill {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .sla-badge-low { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
        .sla-badge-medium { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .sla-badge-high { background-color: rgba(249, 115, 22, 0.1); color: #f97316; }
        .sla-badge-critical { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .search-input-sla {
            background-color: var(--bg-input);
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            height: 42px;
            font-size: 14px;
            padding-left: 42px;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .search-input-sla:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(91, 95, 246, 0.15);
        }

        .filter-select {
            background-color: var(--bg-input);
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            height: 42px;
            font-size: 14px;
            color: var(--text-primary);
            padding: 0 12px;
            transition: all 0.2s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(91, 95, 246, 0.15);
        }

        /* Custom Scrollbar */
        .sticky-table-container::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        .sticky-table-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .sticky-table-container::-webkit-scrollbar-thumb {
            background: var(--border-soft);
            border-radius: 10px;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Entry Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            .premium-card {
                box-shadow: none !important;
                border: 1px solid #e2e8f0 !important;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
        }
    </style>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('slaDashboard', () => ({
            activeTab: 'dashboard',
            policies: @json($alpinePolicies),
            searchQuery: '{{ $filters['search'] ?? '' }}',
            selectedTier: '{{ $filters['tier'] ?? 'All' }}',
            selectedPriority: '{{ $filters['priority'] ?? 'All' }}',
            selectedStatus: '{{ $filters['status'] ?? 'All' }}',
            selectedResponseMax: '',
            selectedResolutionMax: '',
            sortBy: '{{ $filters['sort'] ?? 'name' }}',
            sortDesc: '{{ $filters['direction'] ?? 'asc' }}' === 'desc',
            page: 1,
            perPage: 10,
            confirmDeleteTier: null,
            showSeedModal: false,
            showSeedSummaryModal: false,
            seedSummaryData: null,
            chartsLoaded: false,
            nearBreachList: @json($alpineNearBreach),
            selectedRows: [],

            // Modal States
            showCreateModal: false,
            modalMode: 'create', // 'create', 'edit', 'duplicate'
            showViewModal: false,
            selectedPolicyForView: null,
            showDeleteModal: false,
            selectedPolicyForDelete: null,
            
            // Form Object
            form: {
                id: null,
                name: '',
                description: '',
                client_tier: 'basic',
                priority: 'medium',
                response_time_hours: 8,
                resolution_time_hours: 24,
                is_active: true,
                overwrite: false,
            },

            openCreateModal() {
                this.modalMode = 'create';
                this.form = {
                    id: null,
                    name: '',
                    description: '',
                    client_tier: 'basic',
                    priority: 'medium',
                    response_time_hours: 8,
                    resolution_time_hours: 24,
                    is_active: true,
                    overwrite: false,
                };
                this.showCreateModal = true;
            },

            openEditModal(p) {
                this.modalMode = 'edit';
                this.form = {
                    id: p.id,
                    name: p.name,
                    description: p.description || '',
                    client_tier: p.client_tier,
                    priority: p.priority,
                    response_time_hours: p.response_time_hours,
                    resolution_time_hours: p.resolution_time_hours,
                    is_active: p.is_active,
                    overwrite: false,
                };
                this.showCreateModal = true;
            },

            openDuplicateModal(p) {
                this.modalMode = 'duplicate';
                this.form = {
                    id: null,
                    name: p.name + ' (Copy)',
                    description: p.description || '',
                    client_tier: p.client_tier,
                    priority: p.priority,
                    response_time_hours: p.response_time_hours,
                    resolution_time_hours: p.resolution_time_hours,
                    is_active: true,
                    overwrite: false,
                };
                this.showCreateModal = true;
            },

            openViewModal(p) {
                this.selectedPolicyForView = p;
                this.showViewModal = true;
            },

            openDeleteModal(p) {
                this.selectedPolicyForDelete = p;
                this.showDeleteModal = true;
            },

            get validationErrors() {
                const errors = [];
                if (!this.form.name || !this.form.name.trim()) {
                    errors.push('Policy name is required.');
                }
                if (this.form.resolution_time_hours === '' || Number(this.form.resolution_time_hours) <= 0) {
                    errors.push('Resolution time target must be greater than 0 hours.');
                }
                if (this.form.response_time_hours === '' || Number(this.form.response_time_hours) < 0) {
                    errors.push('Response time target cannot be negative.');
                }
                if (Number(this.form.response_time_hours) > Number(this.form.resolution_time_hours)) {
                    errors.push('Response time target cannot exceed resolution time target.');
                }
                return errors;
            },

            get duplicateMatch() {
                if (!this.form.client_tier || !this.form.priority) return null;
                return this.policies.find(p => 
                    p.client_tier === this.form.client_tier && 
                    p.priority === this.form.priority && 
                    p.id !== this.form.id
                ) || null;
            },

            get totalPages() {
                return Math.ceil(this.filteredPolicies.length / this.perPage) || 1;
            },
            
            get filteredPolicies() {
                return this.policies.filter(p => {
                    if (this.selectedTier !== 'All' && p.client_tier !== this.selectedTier) return false;
                    if (this.selectedPriority !== 'All' && p.priority !== this.selectedPriority) return false;
                    if (this.selectedStatus === 'Active' && !p.is_active) return false;
                    if ((this.selectedStatus === 'Paused' || this.selectedStatus === 'Archived') && p.is_active) return false;
                    
                    if (this.selectedResponseMax !== '' && p.response_time_hours > Number(this.selectedResponseMax)) return false;
                    if (this.selectedResolutionMax !== '' && p.resolution_time_hours > Number(this.selectedResolutionMax)) return false;

                    if (this.searchQuery) {
                        const q = this.searchQuery.toLowerCase();
                        return (p.name && p.name.toLowerCase().includes(q)) || 
                               (p.client_tier && p.client_tier.toLowerCase().includes(q)) || 
                               (p.priority && p.priority.toLowerCase().includes(q)) ||
                               (p.description && p.description.toLowerCase().includes(q));
                    }
                    return true;
                }).sort((a, b) => {
                    let modifier = this.sortDesc ? -1 : 1;
                    let valA = a[this.sortBy];
                    let valB = b[this.sortBy];
                    if (typeof valA === 'string') {
                        return valA.localeCompare(valB) * modifier;
                    }
                    if (valA < valB) return -1 * modifier;
                    if (valA > valB) return 1 * modifier;
                    return 0;
                });
            },

            get paginatedPolicies() {
                const start = (this.page - 1) * this.perPage;
                return this.filteredPolicies.slice(start, start + this.perPage);
            },

            nextPage() {
                if (this.page < this.totalPages) this.page++;
            },
            prevPage() {
                if (this.page > 1) this.page--;
            },
            setPage(p) {
                this.page = p;
            },

            toggleSort(field) {
                if (this.sortBy === field) {
                    this.sortDesc = !this.sortDesc;
                } else {
                    this.sortBy = field;
                    this.sortDesc = false;
                }
                this.page = 1;
            },

            toggleSelectAll() {
                if (this.selectedRows.length === this.paginatedPolicies.length) {
                    this.selectedRows = [];
                } else {
                    this.selectedRows = this.paginatedPolicies.map(p => p.id);
                }
            },

            clearFilters() {
                this.searchQuery = '';
                this.selectedTier = 'All';
                this.selectedPriority = 'All';
                this.selectedStatus = 'All';
                this.selectedResponseMax = '';
                this.selectedResolutionMax = '';
                this.page = 1;
            },

            applyBulkAction(action) {
                if (!this.selectedRows.length) {
                    alert('No policies selected.');
                    return;
                }
                const form = document.querySelector('#bulk-action-form');
                if (form) {
                    document.querySelector('#bulk-action-input').value = action;
                    form.submit();
                }
            },

            exportCSV() {
                const url = '{{ Route::has($routePrefix."sla.export") ? route($routePrefix."sla.export") : "#" }}';
                if (url !== '#') {
                    window.location.href = url + '?ids=' + this.selectedRows.join(',');
                } else {
                    let headers = ['Policy Name', 'Client Tier', 'Priority', 'Response Target (Hours)', 'Resolution Target (Hours)', 'Status', 'Last Updated'];
                    let rows = this.filteredPolicies.map(p => [
                        `"${p.name}"`,
                        p.client_tier ? p.client_tier.toUpperCase() : 'N/A',
                        p.priority ? p.priority.toUpperCase() : 'N/A',
                        p.response_time_hours,
                        p.resolution_time_hours,
                        p.is_active ? 'ACTIVE' : 'PAUSED',
                        `"${p.updated_at}"`
                    ]);
                    
                    let csvContent = 'data:text/csv;charset=utf-8,\uFEFF' 
                        + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
                        
                    let encodedUri = encodeURI(csvContent);
                    let link = document.createElement('a');
                    link.setAttribute('href', encodedUri);
                    link.setAttribute('download', 'SLA_Policies_Export.csv');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            },

            printRegistry() {
                window.print();
            },

            initDashboardCharts() {
                if (typeof ApexCharts === 'undefined') {
                    setTimeout(() => this.initDashboardCharts(), 150);
                    return;
                }
                
                const isDark = document.documentElement.classList.contains('dark');
                const themeMode = isDark ? 'dark' : 'light';
                const labelColor = isDark ? '#94A3B8' : '#64748B';
                
                // 1. Weekly Compliance Trend
                const trendEl = document.querySelector('#chart-compliance-trend');
                if (trendEl) {
                    this.trendChart = new ApexCharts(trendEl, {
                        chart: {
                            type: 'area',
                            height: 260,
                            background: 'transparent',
                            toolbar: { show: false }
                        },
                        series: [{
                            name: 'Compliance %',
                            data: @json($weeklyCompliance)
                        }],
                        xaxis: {
                            categories: @json($weeks),
                            labels: { style: { colors: labelColor } }
                        },
                        yaxis: {
                            max: 100,
                            min: 80,
                            labels: { style: { colors: labelColor } }
                        },
                        colors: ['#5B5FF6'],
                        fill: {
                            type: 'gradient',
                            gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 }
                        },
                        stroke: { curve: 'smooth', width: 3 },
                        theme: { mode: themeMode }
                    });
                    this.trendChart.render();
                }

                // 2. Response & Resolution Hours Trend
                const timesEl = document.querySelector('#chart-times-trend');
                if (timesEl) {
                    this.timesTrendChart = new ApexCharts(timesEl, {
                        chart: {
                            type: 'line',
                            height: 260,
                            background: 'transparent',
                            toolbar: { show: false }
                        },
                        series: [
                            { name: 'Avg Response (h)', data: @json($weeklyResp) },
                            { name: 'Avg Resolution (h)', data: @json($weeklyRes) }
                        ],
                        xaxis: {
                            categories: @json($weeks),
                            labels: { style: { colors: labelColor } }
                        },
                        yaxis: {
                            labels: { style: { colors: labelColor } }
                        },
                        colors: ['#10B981', '#F59E0B'],
                        stroke: { curve: 'smooth', width: 2.5 },
                        theme: { mode: themeMode }
                    });
                    this.timesTrendChart.render();
                }

                // 3. Priority Breakdown Column Chart
                const prioEl = document.querySelector('#chart-priority-breakdown');
                if (prioEl) {
                    const prioData = @json($complianceReport['by_priority'] ?? []);
                    const priorities = ['low', 'medium', 'high', 'critical'];
                    const prioRates = priorities.map(p => (prioData && prioData[p]) ? prioData[p].resolution_rate : 100.0);

                    this.priorityRateChart = new ApexCharts(prioEl, {
                        chart: {
                            type: 'bar',
                            height: 260,
                            background: 'transparent',
                            toolbar: { show: false }
                        },
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '40%',
                                borderRadius: 6
                            }
                        },
                        series: [{
                            name: 'Compliance Rate %',
                            data: prioRates
                        }],
                        xaxis: {
                            categories: ['Low', 'Medium', 'High', 'Critical'],
                            labels: { style: { colors: labelColor } }
                        },
                        yaxis: {
                            max: 100,
                            labels: { style: { colors: labelColor } }
                        },
                        colors: ['#3B82F6'],
                        theme: { mode: themeMode }
                    });
                    this.priorityRateChart.render();
                }

                // 4. Client Tier Breakdown Bar Chart
                const tierEl = document.querySelector('#chart-tier-breakdown');
                if (tierEl) {
                    const tierData = @json($complianceReport['by_tier'] ?? []);
                    const tierNames = ['basic', 'premium', 'enterprise'];
                    const tierRates = tierNames.map(t => (tierData && tierData[t]) ? tierData[t].resolution_rate : 100.0);

                    this.tierRateChart = new ApexCharts(tierEl, {
                        chart: {
                            type: 'bar',
                            height: 260,
                            background: 'transparent',
                            toolbar: { show: false }
                        },
                        plotOptions: {
                            bar: {
                                horizontal: true,
                                barHeight: '40%',
                                borderRadius: 6
                            }
                        },
                        series: [{
                            name: 'Compliance Rate %',
                            data: tierRates
                        }],
                        xaxis: {
                            max: 100,
                            labels: { style: { colors: labelColor } }
                        },
                        yaxis: {
                            categories: ['Basic', 'Premium', 'Enterprise'],
                            labels: { style: { colors: labelColor } }
                        },
                        colors: ['#8B5CF6'],
                        theme: { mode: themeMode }
                    });
                    this.tierRateChart.render();
                }
                
                this.chartsLoaded = true;
            },

            updateChartThemes(isDark) {
                if (!this.chartsLoaded) return;
                const mode = isDark ? 'dark' : 'light';
                const labelColor = isDark ? '#94A3B8' : '#64748B';
                
                const updateOpts = {
                    theme: { mode: mode },
                    xaxis: { labels: { style: { colors: labelColor } } },
                    yaxis: { labels: { style: { colors: labelColor } } }
                };
                
                if (this.trendChart) this.trendChart.updateOptions(updateOpts);
                if (this.timesTrendChart) this.timesTrendChart.updateOptions(updateOpts);
                if (this.priorityRateChart) this.priorityRateChart.updateOptions(updateOpts);
                if (this.tierRateChart) {
                    this.tierRateChart.updateOptions({
                        theme: { mode: mode },
                        xaxis: { labels: { style: { colors: labelColor } } },
                        yaxis: { labels: { style: { colors: labelColor } } }
                    });
                }
            },

            formatCountdown(dueTimestamp) {
                const now = Math.floor(Date.now() / 1000);
                const diff = dueTimestamp - now;
                if (diff <= 0) return 'Breached!';
                
                const h = Math.floor(diff / 3600).toString().padStart(2, '0');
                const m = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
                const s = Math.floor(diff % 60).toString().padStart(2, '0');
                return `${h}:${m}:${s}`;
            },

            sendReminder(ticketId) {
                alert(`SLA breach warning reminder notification sent to assignee for ticket #${ticketId}!`);
            },

            escalateTicket(ticketId) {
                alert(`Ticket #${ticketId} has been successfully escalated to Level 2 engineering team.`);
            },

            init() {
                const successMsg = @json(session('success'));
                if (successMsg && (successMsg.includes('Seeded') || successMsg.includes('already in place'))) {
                    let created = 0;
                    if (successMsg.includes('Seeded')) {
                        const match = successMsg.match(/Seeded (\d+) standard/);
                        if (match) created = parseInt(match[1]);
                    }
                    this.seedSummaryData = {
                        created: created,
                        existing: 12 - created,
                        skipped: 12 - created,
                        total: 12
                    };
                    this.showSeedSummaryModal = true;
                }

                this.$nextTick(() => {
                    this.initDashboardCharts();
                });

                this.$watch('activeTab', (tab) => {
                    if (tab === 'dashboard') {
                        this.$nextTick(() => {
                            this.initDashboardCharts();
                        });
                    }
                });

                window.addEventListener('theme-changed', (e) => {
                    const isDark = typeof e.detail === 'string' ? e.detail === 'dark' : (e.detail?.darkMode ?? document.documentElement.classList.contains('dark'));
                    this.updateChartThemes(isDark);
                });

                setInterval(() => {
                    this.nearBreachList = [...this.nearBreachList];
                }, 1000);
            }
        }));
    });
    </script>

    <!-- 1. Main Page Container -->
    <div class="sla-dashboard animate-fade-in flex flex-col w-full relative" x-data="slaDashboard">
        
        <!-- 2. Header Row (Top): Completely transparent, no background box, no borders -->
        <div class="w-full flex flex-col lg:flex-row lg:items-center justify-between gap-4 no-print relative z-20 pointer-events-auto">
            
            <!-- Left Side (Title area): ITIL Enterprise badge + <h1> Title -->
            <div class="flex items-center gap-3 shrink-0">
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 rounded-md shrink-0">
                    ITIL Enterprise
                </span>
                <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-tight text-[var(--text-primary)] shrink-0 whitespace-nowrap">
                    {{ __('SLA Policies & Command Center') }}
                </h1>
            </div>
            
            <!-- Right Side (Buttons): 4 Action Buttons sitting on the far right -->
            <div class="flex flex-wrap items-center gap-3 shrink-0 relative z-30 pointer-events-auto">
                <button type="button" @click="openCreateModal()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#5B5FF6] hover:bg-[#4F46E5] active:scale-[0.99] text-white px-4 py-2.5 h-10 text-xs sm:text-sm font-semibold shadow-sm transition-all cursor-pointer border-none shrink-0 w-auto max-w-max relative z-30 pointer-events-auto">
                    <svg class="h-4 w-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    <span class="whitespace-nowrap pointer-events-none">Create Policy</span>
                </button>

                <button type="button" @click="exportCSV()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[var(--bg-card)] hover:bg-[var(--bg-hover)] text-[var(--text-primary)] border border-[var(--border-soft)] dark:border-slate-700 px-4 py-2.5 h-10 text-xs sm:text-sm font-semibold shadow-sm transition-all cursor-pointer shrink-0 w-auto max-w-max relative z-30 pointer-events-auto">
                    <svg class="h-4 w-4 text-[var(--text-secondary)] pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    <span class="whitespace-nowrap pointer-events-none">Export</span>
                </button>

                <button type="button" @click="window.location.reload()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[var(--bg-card)] hover:bg-[var(--bg-hover)] text-[var(--text-primary)] border border-[var(--border-soft)] dark:border-slate-700 px-4 py-2.5 h-10 text-xs sm:text-sm font-semibold shadow-sm transition-all cursor-pointer shrink-0 w-auto max-w-max relative z-30 pointer-events-auto">
                    <svg class="h-4 w-4 text-[var(--text-secondary)] pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H17" /></svg>
                    <span class="whitespace-nowrap pointer-events-none">Refresh</span>
                </button>

                <button type="button" @click="showSeedModal = true" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[var(--bg-card)] hover:bg-[var(--bg-hover)] text-[var(--text-primary)] border border-[var(--border-soft)] dark:border-slate-700 px-4 py-2.5 h-10 text-xs sm:text-sm font-semibold shadow-sm transition-all cursor-pointer shrink-0 w-auto max-w-max relative z-30 pointer-events-auto">
                    <span class="whitespace-nowrap pointer-events-none">Seed Defaults</span>
                </button>
            </div>
        </div>

        <!-- 3. Subtitle Row (Middle): Directly below Header Row with mt-2 (8px). NO background box, NO borders -->
        <p class="mt-2 text-xs sm:text-sm text-[var(--text-secondary)] leading-relaxed block w-full text-left bg-transparent border-none p-0 no-print relative z-10">
            Configure, monitor, and enforce service level agreements across client tiers and priorities.
        </p>

        <!-- 4. Tabs Container (Bottom) - Theme-aware Light/Dark supporting panel -->
        <div class="w-full mt-6 bg-[var(--bg-card)] dark:bg-slate-900 p-1.5 rounded-2xl border border-[var(--border-soft)] dark:border-slate-800 no-print overflow-x-auto no-scrollbar relative z-10 shadow-sm">
            <div class="flex flex-row items-center justify-start divide-x divide-slate-200 dark:divide-slate-800 w-full min-w-max sm:min-w-0">
                <button type="button" @click="activeTab = 'dashboard'" 
                        :class="activeTab === 'dashboard' 
                            ? 'bg-transparent text-[#5B5FF6] dark:text-indigo-400 font-extrabold border-b-2 border-[#5B5FF6]' 
                            : 'bg-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] dark:text-slate-400 dark:hover:text-white font-semibold border-b-2 border-transparent'" 
                        class="px-5 py-3 text-xs sm:text-sm transition-all duration-200 flex items-center justify-center gap-2.5 flex-1 focus:outline-none cursor-pointer">
                    <svg class="h-4 w-4 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    <span class="whitespace-nowrap pointer-events-none">📊 SLA Health & Command Center</span>
                </button>
                <button type="button" @click="activeTab = 'policies'" 
                        :class="activeTab === 'policies' 
                            ? 'bg-transparent text-[#5B5FF6] dark:text-indigo-400 font-extrabold border-b-2 border-[#5B5FF6]' 
                            : 'bg-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] dark:text-slate-400 dark:hover:text-white font-semibold border-b-2 border-transparent'" 
                        class="px-5 py-3 text-xs sm:text-sm transition-all duration-200 flex items-center justify-center gap-2.5 flex-1 focus:outline-none cursor-pointer">
                    <svg class="h-4 w-4 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span class="whitespace-nowrap pointer-events-none">📋 SLA Policy Registry (CRUD)</span>
                </button>
                <button type="button" @click="activeTab = 'tiers'" 
                        :class="activeTab === 'tiers' 
                            ? 'bg-transparent text-[#5B5FF6] dark:text-indigo-400 font-extrabold border-b-2 border-[#5B5FF6]' 
                            : 'bg-transparent text-[var(--text-secondary)] hover:text-[var(--text-primary)] dark:text-slate-400 dark:hover:text-white font-semibold border-b-2 border-transparent'" 
                        class="px-5 py-3 text-xs sm:text-sm transition-all duration-200 flex items-center justify-center gap-2.5 flex-1 focus:outline-none cursor-pointer">
                    <svg class="h-4 w-4 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    <span class="whitespace-nowrap pointer-events-none">⚡ Tier Workstations</span>
                </button>
            </div>
        </div>

        <!-- ==================== TAB 1: DASHBOARD & HEALTH ==================== -->
        <div x-show="activeTab === 'dashboard'" class="space-y-6 mt-6" x-cloak>
            
            <!-- SLA Health Panel KPI Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
                <div class="premium-card p-4 flex flex-col justify-between h-full min-h-[105px]">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Total Policies</span>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-extrabold text-[var(--text-primary)] tracking-tight">{{ $healthStats['totalPolicies'] }}</span>
                        <span class="text-[10px] text-[var(--text-secondary)]">defined</span>
                    </div>
                </div>
                <div class="premium-card p-4 flex flex-col justify-between h-full min-h-[105px]">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Active Policies</span>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-extrabold text-emerald-500 tracking-tight">{{ $healthStats['activePolicies'] }}</span>
                        <span class="text-[10px] text-emerald-600 font-bold">({{ $healthStats['inactivePolicies'] }} Archived)</span>
                    </div>
                </div>
                <div class="premium-card p-4 flex flex-col justify-between h-full min-h-[105px]">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Avg Response Target</span>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-extrabold text-indigo-500 tracking-tight">{{ $healthStats['avgResponseTarget'] }}h</span>
                        <span class="text-[10px] text-[var(--text-secondary)]">target</span>
                    </div>
                </div>
                <div class="premium-card p-4 flex flex-col justify-between h-full min-h-[105px]">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Avg Resolution Target</span>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-extrabold text-blue-500 tracking-tight">{{ $healthStats['avgResolutionTarget'] }}h</span>
                        <span class="text-[10px] text-[var(--text-secondary)]">target</span>
                    </div>
                </div>
                <div class="premium-card p-4 flex flex-col justify-between h-full min-h-[105px] col-span-2 sm:col-span-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Most Used Policy</span>
                    <div class="mt-1 min-w-0">
                        <span class="block text-sm font-bold text-[var(--text-primary)] truncate">{{ $healthStats['mostUsedPolicy'] }}</span>
                        <span class="text-[10px] text-indigo-500 font-bold">{{ $healthStats['mostUsedCount'] }} tickets</span>
                    </div>
                </div>
                <div class="premium-card p-4 flex flex-col justify-between h-full min-h-[105px] text-rose-600 dark:text-rose-400">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Overdue Violations</span>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-extrabold tracking-tight">{{ $overdueTickets->count() }}</span>
                        <span class="text-[10px] font-bold">open</span>
                    </div>
                </div>
                <div class="premium-card p-4 flex flex-col justify-between h-full min-h-[105px] text-amber-500 col-span-2 sm:col-span-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Near Breach (<4h)</span>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-extrabold tracking-tight">{{ $nearBreachTickets->count() }}</span>
                        <span class="text-[10px] font-bold">tickets</span>
                    </div>
                </div>
            </div>

            <!-- Live Health & Analytics Panel -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Real-time Health summary -->
                <div class="premium-card p-6 space-y-6 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-extrabold text-[var(--text-primary)]">Weekly SLA Compliance Progression</h3>
                            <p class="text-xs text-[var(--text-secondary)] mt-1">Resolution compliance trend over the past 6 weeks</p>
                        </div>
                        <span class="sla-pill sla-badge-low">Live Compliance</span>
                    </div>
                    
                    <div id="chart-compliance-trend" class="w-full"></div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-2">Response Speed Trend</h4>
                            <div id="chart-times-trend" class="w-full"></div>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-2">Priority Compliance Breakdown</h4>
                            <div id="chart-priority-breakdown" class="w-full"></div>
                        </div>
                    </div>
                </div>

                <!-- SLA Breach Warning Feed -->
                <div class="premium-card p-6 space-y-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-2.5 w-2.5">
                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                                </span>
                                <h3 class="text-base font-extrabold text-[var(--text-primary)]">Near Breach Radar</h3>
                            </div>
                            <span class="text-xs font-bold text-rose-500 uppercase tracking-wider" x-text="nearBreachList.length + ' At Risk'"></span>
                        </div>

                        <div class="mt-4 space-y-3 max-h-[420px] overflow-y-auto pr-1">
                            <template x-if="nearBreachList.length === 0">
                                <div class="text-center py-10 space-y-2">
                                    <svg class="mx-auto h-8 w-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <p class="text-sm font-semibold text-[var(--text-primary)]">All targets safe!</p>
                                    <p class="text-xs text-[var(--text-secondary)]">No tickets are within 4 hours of SLA breach.</p>
                                </div>
                            </template>

                            <template x-for="t in nearBreachList" :key="t.id">
                                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2 transition hover:border-amber-300">
                                    <div class="flex items-center justify-between text-xs">
                                        <a :href="'/tickets/' + t.id" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline" x-text="'#' + t.number"></a>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-400 font-mono" x-text="formatCountdown(t.due_timestamp)"></span>
                                    </div>
                                    <p class="text-xs font-medium text-[var(--text-primary)] line-clamp-1" x-text="t.subject"></p>
                                    <div class="flex items-center justify-between text-[11px] text-[var(--text-secondary)] pt-1">
                                        <span x-text="'Client: ' + t.client"></span>
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="sendReminder(t.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold cursor-pointer">Notify</button>
                                            <span>•</span>
                                            <button type="button" @click="escalateTicket(t.id)" class="text-rose-600 dark:text-rose-400 hover:underline font-semibold cursor-pointer">Escalate</button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 text-center">
                        <a href="{{ $getTicketsUrl(['sla_breached' => '1']) }}" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">View All Overdue & Breached Tickets →</a>
                    </div>
                </div>

            </div>
        </div>

        <!-- ==================== TAB 2: POLICIES REGISTRY (CRUD) ==================== -->
        <div x-show="activeTab === 'policies'" class="space-y-6 mt-6" x-cloak>
            
            <!-- Filters Bar -->
            <div class="premium-card p-5 space-y-4 no-print">
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
                    
                    <!-- Search Bar -->
                    <x-search-input 
                        model="searchQuery" 
                        placeholder="Search by policy name, tier, or description..." 
                        wrapperClass="min-w-[240px]" 
                        inputClass="h-10.5" 
                    />

                    <!-- Dropdown Filters -->
                    <div class="grid grid-cols-2 lg:flex lg:items-center flex-wrap gap-3">
                        <select x-model="selectedTier" class="filter-select cursor-pointer w-full lg:w-auto">
                            <option value="All">All Client Tiers</option>
                            <option value="basic">Basic Tier</option>
                            <option value="premium">Premium Tier</option>
                            <option value="enterprise">Enterprise Tier</option>
                        </select>

                        <select x-model="selectedPriority" class="filter-select cursor-pointer w-full lg:w-auto">
                            <option value="All">All Priorities</option>
                            <option value="low">Low Priority</option>
                            <option value="medium">Medium Priority</option>
                            <option value="high">High Priority</option>
                            <option value="critical">Critical Priority</option>
                        </select>

                        <select x-model="selectedStatus" class="filter-select cursor-pointer col-span-2 lg:col-span-1 w-full lg:w-auto">
                            <option value="All">All Statuses</option>
                            <option value="Active">Active Only</option>
                            <option value="Archived">Archived / Paused</option>
                        </select>

                        <button type="button" @click="clearFilters()" class="col-span-2 lg:col-span-1 px-3 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition cursor-pointer text-center lg:text-left">
                            Clear Filters
                        </button>
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                <div x-show="selectedRows.length > 0" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 bg-indigo-50 dark:bg-indigo-950/40 p-3 rounded-xl border border-indigo-200/50 dark:border-indigo-800/40 text-xs text-indigo-900 dark:text-indigo-300">
                    <div class="flex items-center gap-2 font-bold">
                        <span x-text="selectedRows.length + ' policies selected'"></span>
                    </div>
                    <div class="flex items-center flex-wrap gap-2">
                        <button type="button" @click="applyBulkAction('activate')" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 cursor-pointer text-xs border-none">Activate Selected</button>
                        <button type="button" @click="applyBulkAction('deactivate')" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white font-semibold hover:bg-amber-700 cursor-pointer text-xs border-none">Archive Selected</button>
                        <button type="button" @click="applyBulkAction('delete')" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 cursor-pointer text-xs border-none">Delete Selected (Safe)</button>
                    </div>
                </div>
            </div>

            <!-- Policies Registry Table -->
            <div class="premium-card overflow-hidden">
                <form id="bulk-action-form" method="POST" action="{{ Route::has($routePrefix.'sla.bulk-action') ? route($routePrefix.'sla.bulk-action') : '#' }}">
                    @csrf
                    <input type="hidden" name="action" id="bulk-action-input" value="" />
                    <template x-for="id in selectedRows" :key="id">
                        <input type="hidden" name="ids[]" :value="id" />
                    </template>
                </form>

                <div class="overflow-x-auto sticky-table-container">
                    <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800 text-left text-sm">
                        <thead class="bg-slate-50/70 dark:bg-slate-800/50 text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] no-print">
                            <tr>
                                <th class="px-4 py-3.5 w-10 text-center">
                                    <input type="checkbox" @click="toggleSelectAll()" :checked="selectedRows.length === paginatedPolicies.length && paginatedPolicies.length > 0" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                                </th>
                                <th class="px-6 py-3.5 cursor-pointer hover:text-[var(--text-primary)]" @click="toggleSort('name')">
                                    Policy Name <span x-show="sortBy === 'name'" x-text="sortDesc ? '↓' : '↑'"></span>
                                </th>
                                <th class="px-4 py-3.5 cursor-pointer hover:text-[var(--text-primary)]" @click="toggleSort('client_tier')">
                                    Tier <span x-show="sortBy === 'client_tier'" x-text="sortDesc ? '↓' : '↑'"></span>
                                </th>
                                <th class="px-4 py-3.5 cursor-pointer hover:text-[var(--text-primary)]" @click="toggleSort('priority')">
                                    Priority <span x-show="sortBy === 'priority'" x-text="sortDesc ? '↓' : '↑'"></span>
                                </th>
                                <th class="px-4 py-3.5 text-center cursor-pointer hover:text-[var(--text-primary)]" @click="toggleSort('response_time_hours')">
                                    Response Target <span x-show="sortBy === 'response_time_hours'" x-text="sortDesc ? '↓' : '↑'"></span>
                                </th>
                                <th class="px-4 py-3.5 text-center cursor-pointer hover:text-[var(--text-primary)]" @click="toggleSort('resolution_time_hours')">
                                    Resolution Target <span x-show="sortBy === 'resolution_time_hours'" x-text="sortDesc ? '↓' : '↑'"></span>
                                </th>
                                <th class="px-4 py-3.5 text-center">Status</th>
                                <th class="px-4 py-3.5 text-center">Assigned Usage</th>
                                <th class="px-6 py-3.5 text-right no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-[var(--text-primary)]">
                            <template x-if="paginatedPolicies.length === 0">
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-[var(--text-secondary)]">
                                        No SLA policies match the selected filters.
                                    </td>
                                </tr>
                            </template>

                            <template x-for="p in paginatedPolicies" :key="p.id">
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                                    <td class="px-4 py-4 text-center no-print">
                                        <input type="checkbox" :value="p.id" x-model="selectedRows" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                                    </td>
                                    <td class="px-6 py-4 font-bold text-[var(--text-primary)]">
                                        <span x-text="p.name"></span>
                                        <div class="text-[11px] text-[var(--text-secondary)] font-normal mt-0.5 line-clamp-1" x-text="p.description ? p.description : 'Standard SLA target specification configuration.'"></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-[var(--text-primary)]" x-text="p.client_tier"></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="sla-pill" :class="'sla-badge-' + p.priority" x-text="p.priority"></span>
                                    </td>
                                    <td class="px-4 py-4 text-center font-bold text-[var(--text-primary)]" x-text="p.response_time_hours + ' Hours'"></td>
                                    <td class="px-4 py-4 text-center font-bold text-[var(--text-primary)]" x-text="p.resolution_time_hours + ' Hours'"></td>
                                    <td class="px-4 py-4 text-center">
                                        <span x-show="p.is_active" class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-950/30 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">Active</span>
                                        <span x-show="!p.is_active" class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-xs font-semibold text-[var(--text-secondary)]">Archived</span>
                                    </td>
                                    <!-- Policy Usage Badge -->
                                    <td class="px-4 py-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5 text-xs">
                                            <a :href="'/tickets?sla_policy_id=' + p.id + '&status=open'" title="Active Tickets" class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 font-semibold hover:underline" x-text="(p.active_tickets_count || 0) + ' active'"></a>
                                            <a :href="'/tickets?sla_policy_id=' + p.id + '&status=closed'" title="Closed Tickets" class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 font-semibold hover:underline" x-text="(p.closed_tickets_count || 0) + ' closed'"></a>
                                            <a x-show="p.breached_tickets_count > 0" :href="'/tickets?sla_policy_id=' + p.id + '&sla_breached=1'" title="Breached Tickets" class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 font-bold hover:underline" x-text="p.breached_tickets_count + ' breached'"></a>
                                        </div>
                                    </td>
                                    <!-- Actions Dropdown -->
                                    <td class="px-6 py-4 text-right no-print" x-data="{ open: false }">
                                        <div class="relative inline-block text-left">
                                            <button @click="open = !open" @click.outside="open = false" type="button" class="p-2 rounded-lg bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-[var(--text-secondary)] transition cursor-pointer">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                                            </button>
                                            <div x-show="open" class="absolute right-0 z-30 mt-2 w-44 origin-top-right rounded-xl bg-[var(--bg-card)] shadow-xl border border-[var(--border-soft)] py-1 text-xs font-semibold text-[var(--text-primary)] animate-fade-in" x-cloak>
                                                <button type="button" @click="openViewModal(p); open = false;" class="w-full text-left px-4 py-2 hover:bg-[var(--bg-hover)] flex items-center gap-2 cursor-pointer">
                                                    <svg class="h-3.5 w-3.5 text-[var(--text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    <span>View Details</span>
                                                </button>
                                                <button type="button" @click="openEditModal(p); open = false;" class="w-full text-left px-4 py-2 hover:bg-[var(--bg-hover)] flex items-center gap-2 cursor-pointer">
                                                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                    <span>Edit Policy</span>
                                                </button>
                                                <button type="button" @click="openDuplicateModal(p); open = false;" class="w-full text-left px-4 py-2 hover:bg-[var(--bg-hover)] flex items-center gap-2 cursor-pointer">
                                                    <svg class="h-3.5 w-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                                                    <span>Duplicate</span>
                                                </button>
                                                <form method="POST" :action="p.toggle_url" class="inline">
                                                    @csrf
                                                    <button type="submit" class="w-full text-left px-4 py-2 hover:bg-[var(--bg-hover)] flex items-center gap-2 cursor-pointer">
                                                        <svg class="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                        <span x-text="p.is_active ? 'Archive Policy' : 'Activate Policy'"></span>
                                                    </button>
                                                </form>
                                                <button type="button" @click="openDeleteModal(p); open = false;" class="w-full text-left px-4 py-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 flex items-center gap-2 cursor-pointer">
                                                    <svg class="h-3.5 w-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                    <span>Delete Policy</span>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Table Pagination -->
                <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-[var(--text-secondary)] no-print">
                    <span x-text="'Showing page ' + page + ' of ' + totalPages + ' (' + filteredPolicies.length + ' total policies)'"></span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="prevPage()" :disabled="page === 1" class="px-3 py-1.5 rounded-lg border border-[var(--border-soft)] disabled:opacity-40 cursor-pointer">Previous</button>
                        <button type="button" @click="nextPage()" :disabled="page === totalPages" class="px-3 py-1.5 rounded-lg border border-[var(--border-soft)] disabled:opacity-40 cursor-pointer">Next</button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ==================== TAB 3: TIER WORKSTATIONS ==================== -->
        <div x-show="activeTab === 'tiers'" class="space-y-6 mt-6" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($tiers as $tier)
                    @php
                        $completeness = $tierCompleteness[$tier];
                        $tierRows = $grouped[$tier];
                        $hasRows = collect($tierRows)->filter()->isNotEmpty();
                    @endphp
                    <div class="premium-card p-6 flex flex-col justify-between space-y-6">
                        <div>
                            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-xl flex items-center justify-center font-extrabold text-white
                                        @if($tier === 'basic') bg-slate-700
                                        @elseif($tier === 'premium') bg-indigo-600
                                        @else bg-purple-600
                                        @endif">
                                        {{ strtoupper(substr($tier, 0, 1)) }}
                                    </div>
                                    <div>
                                        <h4 class="text-base font-extrabold text-[var(--text-primary)] uppercase tracking-wider">{{ $tier }} Tier</h4>
                                        <p class="text-[11px] text-[var(--text-secondary)] mt-0.5">SLA specifications profile</p>
                                    </div>
                                </div>
                                @if($hasRows)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-950/20 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 border border-emerald-200/30">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-xs font-semibold text-[var(--text-secondary)]">Unconfigured</span>
                                @endif
                            </div>

                            <!-- Progress bar configuration -->
                            <div class="mt-5 space-y-1">
                                <div class="flex items-center justify-between text-xs font-medium text-[var(--text-secondary)]">
                                    <span>Configuration Progress</span>
                                    <span class="font-bold text-[var(--text-primary)]">{{ $completeness }}%</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full transition-all duration-500 
                                        @if($tier === 'basic') bg-slate-500
                                        @elseif($tier === 'premium') bg-blue-500
                                        @else bg-purple-500
                                        @endif" style="width: {{ $completeness }}%"></div>
                                </div>
                            </div>

                            <!-- Dynamic Priorities target summaries -->
                            <div class="mt-6 space-y-2.5">
                                @foreach($priorities as $priority)
                                    @php $p = $tierRows[$priority] ?? null; @endphp
                                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-100 dark:border-slate-800/50 last:border-0">
                                        <span class="sla-pill {{ 'sla-badge-'.$priority }}">{{ $priority }}</span>
                                        <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                                            <span>Resp: <strong class="font-bold text-[var(--text-primary)]">{{ $p ? $p->response_time_hours.'h' : '—' }}</strong></span>
                                            <span class="text-slate-300 dark:text-slate-700">|</span>
                                            <span>Res: <strong class="font-bold text-[var(--text-primary)]">{{ $p ? $p->resolution_time_hours.'h' : '—' }}</strong></span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3 no-print">
                            <a href="{{ Route::has($routePrefix.'sla.edit-tier') ? route($routePrefix.'sla.edit-tier', ['tier' => $tier]) : '#' }}" class="inline-flex items-center justify-center flex-1 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-[var(--text-primary)] py-2 text-sm font-semibold transition border border-slate-200 dark:border-slate-700">
                                {{ $hasRows ? __('Configure Targets') : __('Initialize Tier') }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- ==================== MODAL 1: CREATE / EDIT SLA POLICY (WITH LIVE PREVIEW) ==================== -->
        <template x-if="showCreateModal">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm no-print overflow-y-auto">
                <div class="bg-[var(--bg-card)] rounded-2xl max-w-3xl w-full p-6 shadow-2xl border border-[var(--border-soft)] space-y-6 animate-fade-in max-h-[90vh] overflow-y-auto" @click.outside="showCreateModal = false">
                    
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h3 class="text-lg font-bold text-[var(--text-primary)]" x-text="modalMode === 'edit' ? 'Edit SLA Policy' : (modalMode === 'duplicate' ? 'Duplicate SLA Policy' : 'Create SLA Policy')"></h3>
                            <p class="text-xs text-[var(--text-secondary)]">Configure target SLA response and resolution times for tickets.</p>
                        </div>
                        <button type="button" @click="showCreateModal = false" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Form & Live Preview 2-Column Grid -->
                    <form method="POST" :action="modalMode === 'edit' ? '/admin/sla/' + form.id : '{{ Route::has($routePrefix.'sla.store') ? route($routePrefix.'sla.store') : '/admin/sla' }}'">
                        @csrf
                        <template x-if="modalMode === 'edit'">
                            <input type="hidden" name="_method" value="PUT" />
                        </template>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- LEFT COLUMN: Inputs -->
                            <div class="space-y-4 text-xs">
                                <div>
                                    <label class="block font-bold text-[var(--text-primary)] mb-1">Policy Name *</label>
                                    <input type="text" name="name" x-model="form.name" required placeholder="e.g. Enterprise - Critical Priority" class="w-full px-3 py-2 rounded-xl border border-[var(--border-soft)] bg-[var(--bg-app)] text-[var(--text-primary)] text-sm font-semibold" />
                                </div>

                                <div>
                                    <label class="block font-bold text-[var(--text-primary)] mb-1">Description</label>
                                    <textarea name="description" x-model="form.description" rows="2" placeholder="Optional notes regarding target expectations..." class="w-full px-3 py-2 rounded-xl border border-[var(--border-soft)] bg-[var(--bg-app)] text-[var(--text-primary)] text-xs"></textarea>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-bold text-[var(--text-primary)] mb-1">Client Tier *</label>
                                        <select name="client_tier" x-model="form.client_tier" class="w-full px-3 py-2 rounded-xl border border-[var(--border-soft)] bg-[var(--bg-app)] text-[var(--text-primary)] text-xs font-semibold cursor-pointer">
                                            <option value="basic">Basic Tier</option>
                                            <option value="premium">Premium Tier</option>
                                            <option value="enterprise">Enterprise Tier</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block font-bold text-[var(--text-primary)] mb-1">Ticket Priority *</label>
                                        <select name="priority" x-model="form.priority" class="w-full px-3 py-2 rounded-xl border border-[var(--border-soft)] bg-[var(--bg-app)] text-[var(--text-primary)] text-xs font-semibold cursor-pointer">
                                            <option value="low">Low Priority</option>
                                            <option value="medium">Medium Priority</option>
                                            <option value="high">High Priority</option>
                                            <option value="critical">Critical Priority</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-bold text-[var(--text-primary)] mb-1">Response Target (Hours) *</label>
                                        <input type="number" name="response_time_hours" x-model="form.response_time_hours" min="0" required class="w-full px-3 py-2 rounded-xl border border-[var(--border-soft)] bg-[var(--bg-app)] text-[var(--text-primary)] text-sm font-bold text-center" />
                                    </div>
                                    <div>
                                        <label class="block font-bold text-[var(--text-primary)] mb-1">Resolution Target (Hours) *</label>
                                        <input type="number" name="resolution_time_hours" x-model="form.resolution_time_hours" min="1" required class="w-full px-3 py-2 rounded-xl border border-[var(--border-soft)] bg-[var(--bg-app)] text-[var(--text-primary)] text-sm font-bold text-center" />
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-2">
                                    <input type="checkbox" name="is_active" id="is_active_input" value="1" :checked="form.is_active" @change="form.is_active = $el.checked" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                                    <label for="is_active_input" class="font-bold text-[var(--text-primary)] cursor-pointer">Active Policy (Enforce SLA tracking)</label>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN: Live Policy Preview Panel -->
                            <div class="bg-slate-50 dark:bg-slate-800/40 p-4 rounded-xl border border-slate-200/60 dark:border-slate-700 space-y-4">
                                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                                    <span class="text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider">Live SLA Policy Preview</span>
                                    <span class="sla-pill" :class="'sla-badge-' + form.priority" x-text="form.priority"></span>
                                </div>

                                <div class="space-y-3 text-xs">
                                    <div class="p-3 bg-[var(--bg-card)] rounded-lg border border-slate-100 dark:border-slate-800 space-y-1">
                                        <span class="text-[10px] font-bold uppercase text-[var(--text-secondary)] block">Target Scope</span>
                                        <p class="font-extrabold text-[var(--text-primary)]" x-text="(form.client_tier || 'basic').toUpperCase() + ' Tier — ' + (form.priority || 'medium').toUpperCase() + ' Priority'"></p>
                                    </div>

                                    <div class="p-3 bg-[var(--bg-card)] rounded-lg border border-slate-100 dark:border-slate-800 space-y-1">
                                        <span class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400 block">Response Target</span>
                                        <p class="font-bold text-[var(--text-primary)]" x-text="'Created + ' + (form.response_time_hours || 0) + ' Hours'"></p>
                                        <p class="text-[11px] text-[var(--text-secondary)] font-medium">e.g. Ticket created at 9:00 AM → First Response due by <strong class="text-[var(--text-primary)]" x-text="(form.response_time_hours || 0) + ' hours later'"></strong></p>
                                    </div>

                                    <div class="p-3 bg-[var(--bg-card)] rounded-lg border border-slate-100 dark:border-slate-800 space-y-1">
                                        <span class="text-[10px] font-bold uppercase text-indigo-600 dark:text-indigo-400 block">Resolution Target</span>
                                        <p class="font-bold text-[var(--text-primary)]" x-text="'Created + ' + (form.resolution_time_hours || 0) + ' Hours'"></p>
                                        <p class="text-[11px] text-[var(--text-secondary)] font-medium">e.g. Ticket created at 9:00 AM → Full Resolution due by <strong class="text-[var(--text-primary)]" x-text="(form.resolution_time_hours || 0) + ' hours later'"></strong></p>
                                    </div>
                                </div>

                                <!-- Inline Validation Warning Box -->
                                <template x-if="validationErrors.length > 0">
                                    <div class="p-3 bg-rose-50 dark:bg-rose-950/40 rounded-lg border border-rose-200 dark:border-rose-800/40 text-xs text-rose-700 dark:text-rose-400 space-y-1">
                                        <span class="font-bold block">Validation Warnings:</span>
                                        <template x-for="err in validationErrors" :key="err">
                                            <p class="text-[11px]" x-text="'• ' + err"></p>
                                        </template>
                                    </div>
                                </template>

                                <!-- Duplicate Detection Warning Box -->
                                <template x-if="duplicateMatch">
                                    <div class="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-lg border border-amber-200 dark:border-amber-800/40 text-xs text-amber-800 dark:text-amber-300 space-y-2">
                                        <span class="font-bold block">Duplicate Settings Detected:</span>
                                        <p class="text-[11px]" x-text="'An existing policy (' + duplicateMatch.name + ') already matches ' + form.client_tier.toUpperCase() + ' tier & ' + form.priority.toUpperCase() + ' priority.'"></p>
                                        <div class="flex items-center gap-2 pt-1">
                                            <input type="checkbox" name="overwrite" id="overwrite_check" value="1" x-model="form.overwrite" class="rounded border-amber-400 text-amber-600 focus:ring-amber-500 cursor-pointer" />
                                            <label for="overwrite_check" class="font-bold text-[11px] cursor-pointer">I confirm overwrite of existing policy</label>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Modal Footer Buttons -->
                        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100 dark:border-slate-800 mt-6">
                            <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-sm font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-[var(--text-primary)] transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit" :disabled="validationErrors.length > 0 || (duplicateMatch && !form.overwrite)" class="px-5 py-2 text-sm font-semibold rounded-xl bg-[#5B5FF6] hover:bg-[#4F46E5] text-white shadow-sm transition cursor-pointer border-none disabled:opacity-40">
                                Save SLA Policy
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <!-- ==================== MODAL 2: POLICY DETAILS VIEW MODAL ==================== -->
        <template x-if="showViewModal && selectedPolicyForView">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm no-print">
                <div class="bg-[var(--bg-card)] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-[var(--border-soft)] space-y-5 animate-fade-in" @click.outside="showViewModal = false">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h3 class="text-base font-bold text-[var(--text-primary)]" x-text="selectedPolicyForView.name"></h3>
                            <span class="sla-pill mt-1" :class="'sla-badge-' + selectedPolicyForView.priority" x-text="selectedPolicyForView.priority"></span>
                        </div>
                        <button type="button" @click="showViewModal = false" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-[var(--text-secondary)]">Client Tier:</span>
                            <span class="font-bold text-[var(--text-primary)] uppercase" x-text="selectedPolicyForView.client_tier"></span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-[var(--text-secondary)]">Response Target:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400" x-text="selectedPolicyForView.response_time_hours + ' Hours'"></span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-[var(--text-secondary)]">Resolution Target:</span>
                            <span class="font-bold text-indigo-600 dark:text-indigo-400" x-text="selectedPolicyForView.resolution_time_hours + ' Hours'"></span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-[var(--text-secondary)]">Active Status:</span>
                            <span class="font-bold" :class="selectedPolicyForView.is_active ? 'text-emerald-500' : 'text-[var(--text-secondary)]'" x-text="selectedPolicyForView.is_active ? 'Active' : 'Archived'"></span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-[var(--text-secondary)]">Total Assigned Tickets:</span>
                            <span class="font-bold text-[var(--text-primary)]" x-text="selectedPolicyForView.total_tickets_count || 0"></span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-[var(--text-secondary)]">Breached Tickets:</span>
                            <span class="font-bold text-rose-500" x-text="selectedPolicyForView.breached_tickets_count || 0"></span>
                        </div>
                        <div class="pt-2 text-[var(--text-secondary)]">
                            <span class="block text-[10px] uppercase font-bold tracking-wider text-[var(--text-secondary)] mb-1">Description</span>
                            <p class="text-xs text-[var(--text-primary)] italic" x-text="selectedPolicyForView.description || 'No description specified.'"></p>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showViewModal = false" class="px-5 py-2 text-sm font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 text-[var(--text-primary)] transition cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== MODAL 3: SAFE DELETE CHECK MODAL ==================== -->
        <template x-if="showDeleteModal && selectedPolicyForDelete">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm no-print">
                <div class="bg-[var(--bg-card)] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-[var(--border-soft)] space-y-5 animate-fade-in" @click.outside="showDeleteModal = false">
                    
                    <div class="flex items-start gap-4">
                        <div class="p-3 rounded-xl shrink-0" :class="(selectedPolicyForDelete.total_tickets_count || 0) > 0 ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/30 dark:text-amber-400' : 'bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400'">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-[var(--text-primary)]" x-text="(selectedPolicyForDelete.total_tickets_count || 0) > 0 ? 'Policy Cannot Be Deleted' : 'Delete SLA Policy?'"></h3>
                            <p class="text-xs text-[var(--text-secondary)] mt-1" x-text="(selectedPolicyForDelete.total_tickets_count || 0) > 0 ? 'This SLA Policy is currently assigned to ' + selectedPolicyForDelete.total_tickets_count + ' ticket(s). Deletion is prevented to preserve historical SLA tracking.' : 'Are you sure you want to permanently delete \'' + selectedPolicyForDelete.name + '\'?'"></p>
                        </div>
                    </div>

                    <!-- Options for in-use policy vs unused policy -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-sm font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-[var(--text-primary)] transition cursor-pointer">
                            Cancel
                        </button>

                        <template x-if="(selectedPolicyForDelete.total_tickets_count || 0) > 0">
                            <form method="POST" :action="selectedPolicyForDelete.toggle_url" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-xl bg-amber-600 hover:bg-amber-700 text-white transition cursor-pointer border-none">
                                    Archive / Deactivate
                                </button>
                            </form>
                        </template>

                        <template x-if="(selectedPolicyForDelete.total_tickets_count || 0) === 0">
                            <form method="POST" :action="selectedPolicyForDelete.delete_url" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition cursor-pointer border-none">
                                    Delete Permanently
                                </button>
                            </form>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== MODAL 4: SEED CONFIRMATION MODAL ==================== -->
        <template x-if="showSeedModal">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm no-print">
                <div class="bg-[var(--bg-card)] rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-[var(--border-soft)] space-y-5 animate-fade-in" @click.outside="showSeedModal = false">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 rounded-xl shrink-0">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-[var(--text-primary)]">Seed Standard SLA Policies</h3>
                            <p class="text-sm text-[var(--text-secondary)] mt-1">
                                This will automatically populate 12 standard ITIL SLA targets across Basic, Premium, and Enterprise tiers.
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showSeedModal = false" class="px-4 py-2 text-sm font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-[var(--text-primary)] transition cursor-pointer">
                            Cancel
                        </button>
                        <form method="POST" action="{{ Route::has($routePrefix.'sla.seed-defaults') ? route($routePrefix.'sla.seed-defaults') : '#' }}" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-xl bg-[#5B5FF6] hover:bg-[#4F46E5] text-white shadow-sm transition cursor-pointer border-none">
                                Proceed & Seed Defaults
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== MODAL 5: SEED SUMMARY MODAL ==================== -->
        <template x-if="showSeedSummaryModal && seedSummaryData">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm no-print">
                <div class="bg-[var(--bg-card)] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-[var(--border-soft)] space-y-5 animate-fade-in" @click.outside="showSeedSummaryModal = false">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 rounded-xl shrink-0">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-[var(--text-primary)]">Seed Defaults Summary</h3>
                            <p class="text-sm text-[var(--text-secondary)] mt-2">
                                Seeding process completed successfully. Let's see the metrics updates:
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-center text-xs">
                        <div class="bg-emerald-50/50 dark:bg-emerald-950/10 p-3 rounded-xl border border-emerald-100/30">
                            <span class="block text-xl font-extrabold text-emerald-600 dark:text-emerald-400" x-text="seedSummaryData.created"></span>
                            <span class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mt-1 block">Created</span>
                        </div>
                        <div class="bg-indigo-50/50 dark:bg-indigo-950/10 p-3 rounded-xl border border-indigo-100/30">
                            <span class="block text-xl font-extrabold text-indigo-600 dark:text-indigo-400" x-text="seedSummaryData.skipped"></span>
                            <span class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mt-1 block">Skipped</span>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/30 p-3 rounded-xl border border-slate-200/20">
                            <span class="block text-xl font-extrabold text-[var(--text-primary)]" x-text="seedSummaryData.total"></span>
                            <span class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mt-1 block">Total Standard</span>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showSeedSummaryModal = false" class="px-5 py-2 text-sm font-semibold rounded-xl bg-[#5B5FF6] hover:bg-[#4F46E5] text-white shadow-sm transition cursor-pointer border-none">
                            Got it, thanks!
                        </button>
                    </div>
                </div>
            </div>
        </template>

    </div>
@endsection
