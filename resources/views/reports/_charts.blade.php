{{-- Reusable report charts partial --}}
@php
    $chartId = 'c' . substr(md5(mt_rand()), 0, 8);
    $isEntityTrend = isset($entityTrend);
@endphp

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="rounded-xl bg-white shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 px-6 pt-6">
            {{ $isEntityTrend ? ($trendTitle ?? __('Trend by') . ' ' . $topLabel) : __('Ticket Trend') }}
        </h3>
        <div id="{{ $chartId }}_trend" class="px-2 pb-4"></div>
    </div>
    <div class="rounded-xl bg-white shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 px-6 pt-6">{{ __('Top') }} {{ $topLabel }}</h3>
        <div id="{{ $chartId }}_top" class="px-2 pb-4"></div>
    </div>
</div>

<script>
function _initCharts_{{ $chartId }}() {
    if (typeof ApexCharts === 'undefined') { setTimeout(_initCharts_{{ $chartId }}, 50); return; }

    var trendEl = document.getElementById('{{ $chartId }}_trend');
    var topEl = document.getElementById('{{ $chartId }}_top');

    @if($isEntityTrend)
        var entityData = @json($entityTrend);
        if (!entityData.series || entityData.series.length === 0) {
            trendEl.innerHTML = '<p class="text-sm text-gray-500 text-center py-12">No trend data available.</p>';
        } else {
            var series = [];
            for (var s = 0; s < entityData.series.length; s++) {
                var d = [];
                for (var i = 0; i < entityData.series[s].data.length; i++) {
                    d.push(Number(entityData.series[s].data[i]));
                }
                series.push({ name: entityData.series[s].name, data: d });
            }
            var labels = entityData.labels;
            new ApexCharts(trendEl, {
                chart: { type: 'line', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
                series: series,
                xaxis: { categories: labels, labels: { rotate: -45, style: { fontSize: '9px' }, hideOverlappingLabels: true, maxHeight: 80 }, tickAmount: Math.min(labels.length, 10) },
                yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function(v) { return Math.round(v); } } },
                stroke: { curve: 'smooth', width: 2 },
                dataLabels: { enabled: false },
                legend: { position: 'top', fontSize: '12px' },
                grid: { borderColor: '#f3f4f6' }
            }).render();
        }
    @else
        var trendData = @json(array_values(is_array($trend) ? $trend : $trend->toArray()));
        var trendLabels = [];
        var trendCounts = [];
        for (var i = 0; i < trendData.length; i++) {
            trendLabels.push(trendData[i].label);
            trendCounts.push(Number(trendData[i].count));
        }
        new ApexCharts(trendEl, {
            chart: { type: 'area', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
            series: [{ name: 'Tickets', data: trendCounts }],
            xaxis: { categories: trendLabels, labels: { rotate: -45, style: { fontSize: '9px' }, hideOverlappingLabels: true, maxHeight: 80 }, tickAmount: Math.min(trendLabels.length, 10) },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function(v) { return Math.round(v); } } },
            colors: ['#6366f1'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#f3f4f6' }
        }).render();
    @endif

    var topRaw = @json(array_values($topData instanceof \Illuminate\Support\Collection ? $topData->toArray() : (array) $topData));
    if (!topRaw || topRaw.length === 0) {
        topEl.innerHTML = '<p class="text-sm text-gray-500 text-center py-12">No data available.</p>';
    } else {
        var topNames = [];
        var topValues = [];
        for (var j = 0; j < topRaw.length; j++) {
            topNames.push(topRaw[j].name);
            topValues.push(Number(topRaw[j].total));
        }
        new ApexCharts(topEl, {
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            series: [{ name: 'Tickets', data: topValues }],
            xaxis: { categories: topNames, labels: { style: { fontSize: '12px' } } },
            yaxis: { labels: { style: { fontSize: '12px' } } },
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
            colors: ['#6366f1'],
            dataLabels: { enabled: true, style: { fontSize: '12px' } },
            grid: { borderColor: '#f3f4f6' },
            tooltip: { y: { formatter: function(v) { return v + ' tickets'; } } }
        }).render();
    }
}
setTimeout(function() {
    _initCharts_{{ $chartId }}();
    setTimeout(function() { window.dispatchEvent(new Event('resize')); }, 500);
}, 200);
</script>
