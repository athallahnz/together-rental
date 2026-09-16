import { expect, test } from '@playwright/test';
import { loadUatFixture, loginAs } from '../fixtures/uat';

test('UAT-001 authentication: reject invalid credentials, login, and logout', async ({
    page,
}) => {
    const uat = loadUatFixture();

    await page.goto('/login');
    await expect(page).toHaveTitle(/Log in/i);
    await page
        .getByLabel('Email address', { exact: true })
        .fill(uat.users.admin.email);
    await page
        .getByLabel('Password', { exact: true })
        .fill('Definitely-Wrong-Password');
    await page.getByTestId('login-button').click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(
        page
            .getByRole('alert')
            .filter({ hasText: /credentials do not match/i }),
    ).toBeVisible();

    await loginAs(page, uat.users.admin, uat.password);
    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();

    await expect(page).toHaveURL('/');
    await expect(
        page.getByRole('link', { name: 'Masuk', exact: true }),
    ).toBeVisible();
});
