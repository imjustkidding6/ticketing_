import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 180000,
    use: {
        baseURL: 'http://localhost:8005',
        headless: true,
        viewport: { width: 1920, height: 1080 },
        launchOptions: {
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--force-color-profile=srgb']
        },
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                browserName: 'chromium',
            },
        },
    ],
});
