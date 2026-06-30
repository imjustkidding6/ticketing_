import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import { renderMarkdown, renderApexChart, chartHeight } from './assistant-ui';

window.ApexCharts = ApexCharts;

// Shared helpers for the AI assistant widgets (markdown rendering + charts).
window.NexusChat = { renderMarkdown, renderApexChart, chartHeight };

window.Alpine = Alpine;

Alpine.start();
