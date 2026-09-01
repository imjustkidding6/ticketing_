import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import { renderMarkdown, renderApexChart, chartHeight } from './assistant-ui';
import { TourRunner } from './tour';

window.ApexCharts = ApexCharts;

// Shared helpers for the AI assistant widgets (markdown rendering + charts).
window.NexusChat = { renderMarkdown, renderApexChart, chartHeight };

// Guided tours. The runner needs the current route name to know which steps
// belong to this page; the layout publishes it.
window.GuidedTour = new TourRunner({
    currentRoute: document.body?.dataset.route ?? '',
    endpoint: document.body?.dataset.tourEndpoint ?? '',
});

window.Alpine = Alpine;

Alpine.start();
