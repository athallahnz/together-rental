import { readFileSync } from 'node:fs';
import path from 'node:path';
import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

type UatUser = {
    id: number;
    email: string;
};

type UatBranch = {
    id: number;
    code: string;
    name: string;
};

export type UatFixture = {
    generated_at: string;
    base_url: string;
    password: string;
    users: {
        admin: UatUser;
        restricted: UatUser;
        branch_manager: UatUser;
    };
    branches: {
        ponorogo: UatBranch;
        madiun: UatBranch;
    };
};

const fixturePath = path.resolve(
    process.cwd(),
    'storage/framework/testing/e2e-fixtures.json',
);

export function loadUatFixture(): UatFixture {
    try {
        return JSON.parse(readFileSync(fixturePath, 'utf8')) as UatFixture;
    } catch (error) {
        throw new Error(
            `E2E fixture metadata is unavailable at ${fixturePath}. Run npm run e2e:prepare first.`,
            { cause: error },
        );
    }
}

export async function loginAs(
    page: Page,
    user: UatUser,
    password: string,
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill(user.email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByTestId('login-button').click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(
        page.getByRole('heading', {
            name: 'Apa yang perlu diselesaikan hari ini?',
        }),
    ).toBeVisible();
}
