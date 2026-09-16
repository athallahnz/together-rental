import { expect, test } from '@playwright/test';
import { loadUatFixture, loginAs } from '../fixtures/uat';

test('UAT-002 access control: restricted user receives 403 for branch management', async ({
    page,
}) => {
    const uat = loadUatFixture();

    await loginAs(page, uat.users.restricted, uat.password);
    await expect(page.getByRole('link', { name: 'Cabang' })).toHaveCount(0);

    const response = await page.goto('/branches');

    expect(response?.status()).toBe(403);
});

test('UAT-002 access control: super administrator can open branch management', async ({
    page,
}) => {
    const uat = loadUatFixture();

    await loginAs(page, uat.users.admin, uat.password);
    const response = await page.goto('/branches');

    expect(response?.status()).toBe(200);
    await expect(
        page.getByRole('heading', { name: 'Manajemen Cabang' }),
    ).toBeVisible();
});
