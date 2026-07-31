import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 180000,
    use: {
        baseURL: 'http://127.0.0.1:8005',
        headless: true,
        viewport: { width: 1920, height: 1080 },
        executablePath: '/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome',
        launchOptions: {
            executablePath: '/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome',
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--force-color-profile=srgb']
        },
        screenshot: 'off',
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
