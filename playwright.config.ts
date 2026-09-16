import { defineConfig, devices } from '@playwright/test';

const baseURL = 'http://127.0.0.1:8010';

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: 'storage/framework/testing/playwright-results',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    forbidOnly: Boolean(process.env.CI),
    reporter: [
        ['list'],
        [
            'html',
            {
                outputFolder: 'storage/framework/testing/playwright-report',
                open: 'never',
            },
        ],
    ],
    expect: {
        timeout: 10_000,
    },
    use: {
        ...devices['Desktop Chrome'],
        baseURL,
        actionTimeout: 10_000,
        navigationTimeout: 20_000,
        testIdAttribute: 'data-test',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command:
            'herd php artisan --env=e2e serve --host=127.0.0.1 --port=8010',
        url: `${baseURL}/up`,
        reuseExistingServer: false,
        timeout: 120_000,
        stdout: 'pipe',
        stderr: 'pipe',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
