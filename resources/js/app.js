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

// A tour in progress resumes itself as soon as the page is ready. This used to
// hang off the launcher's Alpine init, which meant resuming waited on that
// component's fetch — so a tour could silently fail to pick up on any page
// where the launcher was slow, and on any page that did not render it.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.GuidedTour.resume(), { once: true });
} else {
    window.GuidedTour.resume();
}

window.Alpine = Alpine;

Alpine.start();
