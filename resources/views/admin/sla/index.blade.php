@extends('layouts.admin')

@section('title', 'SLA Policies')

@section('content')
    @php
        // Everything ticket-derived is built by SlaPolicyController::adminOps() and
        // arrives in $adminOps. This view calls no service and computes no figure:
        // the page used to call SlaService itself, which is how it ended up showing
        // whichever tenants happened to be in session.
        $overdueTickets = $adminOps['overdue'];
        $nearBreachTickets = $adminOps['near_breach'];
        $alpineNearBreach = $adminOps['near_breach_alpine'];
        $complianceReport = $adminOps['compliance'];
        $weeks = $adminOps['weeks'];
        $weeklyCompliance = $adminOps['weekly_compliance'];
        $weeklyResp = $adminOps['weekly_resp'];
        $weeklyRes = $adminOps['weekly_res'];

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
            chartsLoaded: false,
            nearBreachList: @json($alpineNearBreach),

            // Modal States. Only the read-only details modal survives: this surface
            // observes, so there is nothing here to create, edit or delete.
            showViewModal: false,
            selectedPolicyForView: null,

            openViewModal(p) {
                this.selectedPolicyForView = p;
                this.showViewModal = true;
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

            clearFilters() {
                this.searchQuery = '';
                this.selectedTier = 'All';
                this.selectedPriority = 'All';
                this.selectedStatus = 'All';
                this.selectedResponseMax = '';
                this.selectedResolutionMax = '';
                this.page = 1;
            },

            exportCSV() {
                // Server-side only. The client-side fallback that used to live here
                // built its own header list, which would now disagree with the
                // server export's Workspace column.
                window.location.href = '{{ route('admin.sla.export') }}';
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
                    const prioRates = priorities.map(p => (prioData && prioData[p]) ? prioData[p].resolution_rate : null);

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
                    const tierRates = tierNames.map(t => (tierData && tierData[t]) ? tierData[t].resolution_rate : null);

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
            
            <!-- Right Side (Buttons): read-only actions only — this surface does not edit -->
            <div class="flex flex-wrap items-center gap-3 shrink-0 relative z-30 pointer-events-auto">
                <button type="button" @click="exportCSV()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[var(--bg-card)] hover:bg-[var(--bg-hover)] text-[var(--text-primary)] border border-[var(--border-soft)] dark:border-slate-700 px-4 py-2.5 h-10 text-xs sm:text-sm font-semibold shadow-sm transition-all cursor-pointer shrink-0 w-auto max-w-max relative z-30 pointer-events-auto">
                    <svg class="h-4 w-4 text-[var(--text-secondary)] pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    <span class="whitespace-nowrap pointer-events-none">Export</span>
                </button>

                <button type="button" @click="window.location.reload()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[var(--bg-card)] hover:bg-[var(--bg-hover)] text-[var(--text-primary)] border border-[var(--border-soft)] dark:border-slate-700 px-4 py-2.5 h-10 text-xs sm:text-sm font-semibold shadow-sm transition-all cursor-pointer shrink-0 w-auto max-w-max relative z-30 pointer-events-auto">
                    <svg class="h-4 w-4 text-[var(--text-secondary)] pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H17" /></svg>
                    <span class="whitespace-nowrap pointer-events-none">Refresh</span>
                </button>

            </div>
        </div>

        <!-- 3. Subtitle Row (Middle): Directly below Header Row with mt-2 (8px). NO background box, NO borders -->
        <p class="mt-2 text-xs sm:text-sm text-[var(--text-secondary)] leading-relaxed block w-full text-left bg-transparent border-none p-0 no-print relative z-10">
            Monitor service level agreements across every workspace, client tier and priority.
            <span class="font-semibold text-[var(--text-primary)]">SLA targets belong to the workspace.</span>
            Open the workspace and use Impersonate to manage them — this page is read-only.
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
                    <span class="whitespace-nowrap pointer-events-none">📋 SLA Policy Registry</span>
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

            <!-- Platform SLA compliance (scope stated, never guessed) -->
            <div class="premium-card p-6 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-base font-extrabold text-[var(--text-primary)]">Platform SLA Compliance</h3>
                        <p class="text-xs text-[var(--text-secondary)] mt-1">
                            {{ $adminOps['scope_label'] }} &middot; {{ $adminOps['window_from'] }} to {{ $adminOps['window_to'] }} (UTC)
                        </p>
                    </div>
                    <span class="sla-pill sla-badge-low">{{ $adminOps['workspaces_total'] }} workspaces</span>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Response Compliance</span>
                        <div class="text-2xl font-extrabold text-[var(--text-primary)] tracking-tight">
                            {{ $adminOps['response_compliance'] !== null ? $adminOps['response_compliance'].'%' : '—' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Resolution Compliance</span>
                        <div class="text-2xl font-extrabold text-[var(--text-primary)] tracking-tight">
                            {{ $adminOps['resolution_compliance'] !== null ? $adminOps['resolution_compliance'].'%' : '—' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Avg Response</span>
                        <div class="text-2xl font-extrabold text-indigo-500 tracking-tight">
                            {{ $adminOps['avg_response_hours'] !== null ? $adminOps['avg_response_hours'].'h' : '—' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-secondary)]">Avg Resolution</span>
                        <div class="text-2xl font-extrabold text-blue-500 tracking-tight">
                            {{ $adminOps['avg_resolution_hours'] !== null ? $adminOps['avg_resolution_hours'].'h' : '—' }}
                        </div>
                    </div>
                </div>

                @if($adminOps['response_compliance'] === null && $adminOps['resolution_compliance'] === null)
                    <p class="text-xs text-[var(--text-secondary)]">No closed tickets with an SLA in this window</p>
                @endif

                <p class="text-xs text-[var(--text-secondary)]">
                    {{ $adminOps['closed_without_sla'] }} closed tickets excluded (no SLA policy)
                    &middot; {{ $adminOps['workspaces_fully_covered'] }} of {{ $adminOps['workspaces_total'] }} workspaces have a complete SLA matrix
                    ({{ \App\Models\SlaPolicy::matrixSlotCount() }} slots)
                </p>
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
            </div>

            <!-- Policies Registry Table -->
            <div class="premium-card overflow-hidden">

                <div class="overflow-x-auto sticky-table-container">
                    <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800 text-left text-sm">
                        <thead class="bg-slate-50/70 dark:bg-slate-800/50 text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] no-print">
                            <tr>
                                <th class="px-4 py-3.5">Workspace</th>
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
                                    <td class="px-4 py-4 font-semibold">
                                        <a :href="p.workspace_url" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-text="p.workspace"></a>
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
                                                <a :href="p.workspace_url" class="w-full text-left px-4 py-2 hover:bg-[var(--bg-hover)] flex items-center gap-2 cursor-pointer">
                                                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                                    <span>Open workspace</span>
                                                </a>
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
                        // Cross-tenant, there is no single policy per (tier, priority) —
                        // $grouped would show whichever workspace sorts first. Show how
                        // many workspaces define the slot and the mean target instead.
                        $cells = $adminOps['tier_cells'][$tier];
                        $definedSlots = collect($cells)->where('workspaces', '>', 0)->count();
                        $completeness = (int) round(($definedSlots / count($priorities)) * 100);
                        $hasRows = $definedSlots > 0;
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
                                    @php $cell = $cells[$priority]; @endphp
                                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-100 dark:border-slate-800/50 last:border-0">
                                        <span class="sla-pill {{ 'sla-badge-'.$priority }}">{{ $priority }}</span>
                                        <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                                            <span>{{ $cell['workspaces'] }} ws</span>
                                            <span class="text-slate-300 dark:text-slate-700">|</span>
                                            <span>Avg resp: <strong class="font-bold text-[var(--text-primary)]">{{ $cell['avg_response'] !== null ? $cell['avg_response'].'h' : '—' }}</strong></span>
                                            <span class="text-slate-300 dark:text-slate-700">|</span>
                                            <span>Avg res: <strong class="font-bold text-[var(--text-primary)]">{{ $cell['avg_resolution'] !== null ? $cell['avg_resolution'].'h' : '—' }}</strong></span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 text-[11px] text-[var(--text-secondary)] no-print">
                            Targets for this tier are set by each workspace.
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

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

    </div>
@endsection
