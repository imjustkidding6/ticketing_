@extends('layouts.admin')

@section('title', 'Executive Reports')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    .reports-container {
        font-family: 'Inter', sans-serif !important;
    }
    
    .premium-card {
        background-color: var(--bg-card, #ffffff);
        border: 1px solid var(--border-soft, rgba(0,0,0,0.06));
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow, 0 1px 3px rgba(0,0,0,0.05));
        transition: all 0.2s ease;
    }
    .premium-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    }
    html.dark .premium-card {
        background-color: #1f2937;
        border-color: #374151;
    }
    html.dark .premium-card:hover {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
    }

    .filter-select {
        background-color: var(--bg-card, #ffffff);
        border: 1px solid var(--border-soft, rgba(0,0,0,0.06));
        border-radius: 12px;
        height: 44px;
        font-size: 14px;
        color: var(--text-primary, #111827);
        padding: 0 16px;
    }
    .dark .filter-select {
        background-color: #1f2937;
        border-color: #374151;
        color: #f9fafb;
    }

    .btn-action-reports {
        height: 44px !important;
        padding: 0 20px !important;
        border-radius: 12px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        transition: all 0.2s ease !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
</style>

<div class="reports-container flex flex-col gap-8 pb-16" x-data="{
    dateRange: '30d',
    tenantFilter: 'All',
    licenseFilter: 'All',
    mrr: 24850,
    tenantsCount: 142,
    ticketsCount: 1942,
    slaRate: 98.4,
    showLoader: false,

    updateFilters() {
        this.showLoader = true;
        // Simulate loading state on filters changes
        setTimeout(() => {
            this.showLoader = false;
            if (this.dateRange === '7d') {
                this.mrr = 23900;
                this.tenantsCount = 138;
                this.ticketsCount = 450;
                this.slaRate = 99.1;
            } else if (this.dateRange === '30d') {
                this.mrr = 24850;
                this.tenantsCount = 142;
                this.ticketsCount = 1942;
                this.slaRate = 98.4;
            } else {
                this.mrr = 29500;
                this.tenantsCount = 156;
                this.ticketsCount = 7420;
                this.slaRate = 97.9;
            }
            this.refreshCharts();
        }, 600);
    },

    exportCSV() {
        let headers = ['Month', 'MRR', 'Active Tenants', 'Tickets Raised', 'SLA Met %'];
        let data = [
            ['Jan', '18500', '110', '1200', '98.1'],
            ['Feb', '19800', '115', '1350', '97.9'],
            ['Mar', '21000', '124', '1400', '98.3'],
            ['Apr', '22400', '130', '1620', '98.5'],
            ['May', '23900', '138', '1780', '99.1'],
            ['Jun', '24850', '142', '1942', '98.4']
        ];
        
        let csvContent = 'data:text/csv;charset=utf-8,' 
            + [headers.join(','), ...data.map(e => e.join(','))].join('\n');
            
        let encodedUri = encodeURI(csvContent);
        let link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'executive_report_' + this.dateRange + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    },

    exportPDF() {
        window.print();
    },

    mrrChart: null,
    ticketsChart: null,
    
    refreshCharts() {
        let isDark = document.documentElement.classList.contains('dark');
        let textCol = isDark ? '#9CA3AF' : '#6B7280';
        let gridCol = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';

        // MRR Chart options
        let mrrOptions = {
            chart: {
                type: 'area',
                height: 320,
                toolbar: { show: false },
                animations: { enabled: true }
            },
            series: [{
                name: 'Monthly Recurring Revenue ($)',
                data: this.dateRange === '7d' ? [22000, 22500, 22900, 23200, 23500, 23700, 23900] : [18500, 19800, 21000, 22400, 23900, this.mrr]
            }],
            xaxis: {
                categories: this.dateRange === '7d' ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                labels: { style: { colors: textCol } }
            },
            yaxis: {
                labels: { style: { colors: textCol } }
            },
            stroke: { curve: 'smooth', width: 3, colors: ['#5B5FF6'] },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            grid: { borderColor: gridCol }
        };

        // Ticket Chart options
        let ticketOptions = {
            chart: {
                type: 'bar',
                height: 320,
                toolbar: { show: false }
            },
            series: [{
                name: 'Tickets Closed',
                data: this.dateRange === '7d' ? [45, 52, 48, 62, 55, 30, 42] : [850, 920, 1040, 1120, 1180, this.ticketsCount]
            }],
            colors: ['#818cf8'],
            xaxis: {
                categories: this.dateRange === '7d' ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                labels: { style: { colors: textCol } }
            },
            yaxis: {
                labels: { style: { colors: textCol } }
            },
            grid: { borderColor: gridCol }
        };

        if (this.mrrChart) this.mrrChart.destroy();
        if (this.ticketsChart) this.ticketsChart.destroy();

        this.mrrChart = new ApexCharts(document.querySelector('#mrr-chart'), mrrOptions);
        this.ticketsChart = new ApexCharts(document.querySelector('#tickets-chart'), ticketOptions);

        this.mrrChart.render();
        this.ticketsChart.render();
    },

    init() {
        this.$nextTick(() => {
            this.refreshCharts();
            window.addEventListener('theme-changed', () => this.refreshCharts());
        });
    }
}">
    
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="page-title text-[var(--text-primary)]">Executive Dashboard</h1>
            <p class="mt-2 text-sm text-[var(--text-secondary)]">
                Enterprise statistics, MRR progression, seat utilization, and tickets resolution speed.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="exportCSV()" class="btn-action-reports bg-[var(--bg-card)] border border-[var(--border-soft)] text-[var(--text-primary)] hover:bg-[var(--bg-hover)] cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text text-[var(--text-secondary)]"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M9 9h1"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>
                <span>Export CSV</span>
            </button>
            <button @click="exportPDF()" class="btn-action-reports bg-[#5B5FF6] text-white hover:bg-[#4752C4] shadow-sm cursor-pointer border-none flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-printer text-white"><path d="M5 18H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2"/><path d="M5 8h10V3H5Z"/><path d="M6 14h8v8H6Z"/></svg>
                <span>Print PDF</span>
            </button>
        </div>
    </div>

    <!-- Filters layout bar -->
    <div class="flex flex-wrap items-center gap-4">
        <!-- Date Selector -->
        <select x-model="dateRange" @change="updateFilters()" class="filter-select cursor-pointer">
            <option value="7d">Last 7 Days</option>
            <option value="30d">Last 30 Days</option>
            <option value="12m">Last 12 Months</option>
        </select>

        <!-- Tenant Selector -->
        <select x-model="tenantFilter" @change="updateFilters()" class="filter-select cursor-pointer">
            <option value="All">All Tenants</option>
            <option value="cliqueha">CliqueHA Enterprise</option>
            <option value="acme">Acme Corp</option>
            <option value="nexus">Nexus Inc</option>
        </select>

        <!-- License Selector -->
        <select x-model="licenseFilter" @change="updateFilters()" class="filter-select cursor-pointer">
            <option value="All">All Licenses</option>
            <option value="enterprise">Enterprise Tier</option>
            <option value="premium">Premium Tier</option>
            <option value="basic">Basic Tier</option>
        </select>
    </div>

    <!-- Loading Skeleton Backdrop overlay -->
    <div class="relative min-h-[500px]">
        <div x-show="showLoader" class="absolute inset-0 bg-white/50 dark:bg-gray-900/50 backdrop-blur-[1px] z-20 flex items-center justify-center rounded-2xl" x-cloak>
            <svg class="animate-spin h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </div>

        <div class="space-y-8">
            <!-- KPI Statistics Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Stat 1 -->
                <div class="premium-card flex flex-col justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Monthly Recurring Revenue (MRR)</span>
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-2" x-text="'$' + number_format(mrr)">$24,850</span>
                </div>
                <!-- Stat 2 -->
                <div class="premium-card flex flex-col justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Active Tenants</span>
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-2" x-text="tenantsCount">142</span>
                </div>
                <!-- Stat 3 -->
                <div class="premium-card flex flex-col justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tickets Handled</span>
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-2" x-text="ticketsCount">1,942</span>
                </div>
                <!-- Stat 4 -->
                <div class="premium-card flex flex-col justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">SLA Compliance Rate</span>
                    <span class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-2" x-text="slaRate + '%'">98.4%</span>
                </div>
            </div>

            <!-- Charts Division -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="premium-card space-y-4">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">MRR Progression Trend</h3>
                    <div id="mrr-chart"></div>
                </div>
                <div class="premium-card space-y-4">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Ticket Volume Metrics</h3>
                    <div id="tickets-chart"></div>
                </div>
            </div>

            <!-- Leaderboard Grid list -->
            <div class="premium-card space-y-4">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Top Tenants by Volume & SLA</h3>
                <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-800">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                        <thead class="bg-gray-50/50 dark:bg-gray-800/40 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3 text-left">Tenant Name</th>
                                <th class="px-6 py-3 text-left">License Plan</th>
                                <th class="px-6 py-3 text-right">Seat Utilization</th>
                                <th class="px-6 py-3 text-right">Tickets Closed</th>
                                <th class="px-6 py-3 text-center">SLA Performance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">CliqueHA Enterprise</td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Enterprise</td>
                                <td class="px-6 py-4 text-right text-gray-900 dark:text-gray-250">92%</td>
                                <td class="px-6 py-4 text-right text-gray-900 dark:text-gray-250">540</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-950/20 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">99.2% Met</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">Acme Corp</td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Premium</td>
                                <td class="px-6 py-4 text-right text-gray-900 dark:text-gray-250">84%</td>
                                <td class="px-6 py-4 text-right text-gray-900 dark:text-gray-250">420</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-950/20 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">98.5% Met</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">Nexus Inc</td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Premium</td>
                                <td class="px-6 py-4 text-right text-gray-900 dark:text-gray-250">78%</td>
                                <td class="px-6 py-4 text-right text-gray-900 dark:text-gray-250">350</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-950/20 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-400">96.8% Met</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function number_format(number) {
        return new Intl.NumberFormat('en-US').format(number);
    }
</script>
@endsection
