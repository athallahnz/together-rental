import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';

const baseURL = 'http://127.0.0.1:8010';
const evidenceMode = process.env.E2E_EVIDENCE === 'true';
const evidenceRoot = process.env.E2E_EVIDENCE_DIR?.trim();
const evidenceTraceMode =
    process.env.E2E_EVIDENCE_TRACE_MODE === 'off' ? 'off' : 'on';

if (evidenceMode && !evidenceRoot) {
    throw new Error(
        'E2E_EVIDENCE_DIR is required when evidence mode is enabled.',
    );
}

const artifactPath = (name: string, fallback: string): string =>
    evidenceRoot ? path.join(evidenceRoot, name) : fallback;

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: artifactPath(
        'test-results',
        'storage/framework/testing/playwright-results',
    ),
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    forbidOnly: Boolean(process.env.CI),
    reporter: [
        ['list'],
        [
            'html',
            {
                outputFolder: artifactPath(
                    'playwright-report',
                    'storage/framework/testing/playwright-report',
                ),
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
        trace: evidenceMode ? evidenceTraceMode : 'retain-on-failure',
        video: evidenceMode ? 'on' : 'retain-on-failure',
        screenshot: evidenceMode ? 'on' : 'only-on-failure',
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
