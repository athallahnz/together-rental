import { expect, test } from '@playwright/test';
import { loadUatFixture, loginAs } from '../fixtures/uat';

test('UAT-003 branch isolation: branch manager sees Ponorogo scope and cannot query Madiun', async ({
    page,
}) => {
    const uat = loadUatFixture();

    await loginAs(page, uat.users.branch_manager, uat.password);
    await expect(
        page
            .getByText(
                `${uat.branches.ponorogo.code} · ${uat.branches.ponorogo.name}`,
                { exact: true },
            )
            .first(),
    ).toBeVisible();

    const bookingMetric = page
        .locator('[data-slot="metric-card"]')
        .filter({ hasText: 'Booking bulan ini' });

    await expect(bookingMetric).toContainText('1');
    await expect(
        page.getByRole('cell', { name: uat.branches.madiun.name }),
    ).toHaveCount(0);

    const response = await page.goto(
        `/dashboard?branch_id=${uat.branches.madiun.id}`,
    );

    expect(response?.status()).toBe(403);
});
