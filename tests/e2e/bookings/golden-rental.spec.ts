import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { loadUatFixture, loginAs } from '../fixtures/uat';
import type { UatFixture } from '../fixtures/uat';

type AvailabilityResponse = {
    available: boolean;
    required: number;
    available_count: number;
    ends_at: string;
};

function bookingField(page: Page, label: string): Locator {
    return page.locator('form').getByText(label, { exact: true }).locator('..');
}

async function selectOption(
    page: Page,
    trigger: Locator,
    optionName: string,
): Promise<void> {
    await trigger.click();
    await page.getByRole('option', { name: optionName, exact: true }).click();
}

async function chooseSearchResult(
    page: Page,
    type: 'pelanggan' | 'produk',
    query: string,
    resultName: string,
): Promise<void> {
    await page
        .getByRole('button', { name: `Pilih ${type}`, exact: true })
        .click();

    const dialog = page.getByRole('dialog', { name: `Pilih ${type}` });

    await dialog.getByPlaceholder(`Cari nama atau kode ${type}...`).fill(query);

    const result = dialog.locator('button').filter({ hasText: resultName });

    await expect(result).toBeVisible();
    await result.click();
}

async function getAvailability(
    page: Page,
    uat: UatFixture,
): Promise<AvailabilityResponse> {
    const golden = uat.golden_rental;
    const response = await page.request.get('/bookings/availability', {
        params: {
            branch_id: uat.branches.ponorogo.id,
            product_id: golden.product.id,
            starts_at: golden.booking.starts_at,
            rate_plan_id: golden.rate_plan.id,
            duration_units: golden.booking.duration_units,
            quantity: golden.booking.quantity,
        },
    });

    expect(response.status()).toBe(200);

    return (await response.json()) as AvailabilityResponse;
}

test('golden rental: customer, availability, booking, DP, and confirmation', async ({
    page,
}) => {
    const uat = loadUatFixture();
    const golden = uat.golden_rental;

    await loginAs(page, uat.users.admin, uat.password);

    await page.goto('/customers');
    await page.getByRole('button', { name: 'Tambah pelanggan' }).click();

    const customerDialog = page.getByRole('dialog', {
        name: 'Tambah pelanggan',
    });

    await customerDialog.getByLabel('Cabang pendaftaran').click();
    await page
        .getByRole('option', {
            name: `${uat.branches.ponorogo.code} · ${uat.branches.ponorogo.name}`,
            exact: true,
        })
        .click();
    await customerDialog.getByLabel('Nama lengkap').fill(golden.customer.name);
    await customerDialog.getByLabel('Telepon').fill(golden.customer.phone);
    await customerDialog.getByLabel('Email').fill(golden.customer.email);
    await customerDialog
        .getByRole('button', { name: 'Buat pelanggan' })
        .click();

    await expect(page).toHaveURL(/\/customers\/\d+$/);
    await expect(
        page.getByRole('heading', {
            name: golden.customer.name,
            exact: true,
        }),
    ).toBeVisible();

    const availabilityBeforeBooking = await getAvailability(page, uat);

    expect(availabilityBeforeBooking).toMatchObject({
        available: true,
        required: golden.booking.quantity,
        available_count: 1,
    });

    await page.goto('/bookings/create');
    await expect(
        page.getByRole('heading', { name: 'Booking baru', exact: true }),
    ).toBeVisible();

    await selectOption(
        page,
        bookingField(page, 'Cabang').getByRole('combobox'),
        uat.branches.ponorogo.name,
    );
    await chooseSearchResult(
        page,
        'pelanggan',
        golden.customer.name,
        golden.customer.name,
    );
    await selectOption(
        page,
        bookingField(page, 'Rate plan').getByRole('combobox'),
        `${golden.rate_plan.name} (1 hari)`,
    );
    await bookingField(page, 'Waktu pengambilan')
        .locator('input[type="datetime-local"]')
        .fill(golden.booking.starts_at);
    await chooseSearchResult(
        page,
        'produk',
        golden.product.sku,
        golden.product.name,
    );
    await page.getByRole('button', { name: 'Simpan booking' }).click();

    await expect(page).toHaveURL(/\/bookings\/\d+$/);
    await expect(page.getByText('draft', { exact: true })).toBeVisible();
    await expect(
        page.getByText(golden.asset.code, { exact: false }),
    ).toBeVisible();

    const availabilityAfterBooking = await getAvailability(page, uat);

    expect(availabilityAfterBooking).toMatchObject({
        available: false,
        required: golden.booking.quantity,
        available_count: 0,
    });

    const paymentCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Terima pembayaran' });

    await paymentCard
        .getByPlaceholder(/DP\/pembayaran sewa/)
        .fill(String(golden.booking.payment_amount));
    await selectOption(
        page,
        paymentCard.getByRole('combobox'),
        golden.payment_method.name,
    );
    await paymentCard
        .getByPlaceholder('Referensi transfer/QRIS')
        .fill(golden.booking.payment_reference);
    await paymentCard
        .getByRole('button', { name: 'Simpan pembayaran' })
        .click();

    const bookingValueCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Nilai booking' });
    const rentalPaidRow = bookingValueCard
        .locator('p')
        .filter({ hasText: 'DP sewa masuk' });

    await expect(rentalPaidRow).toContainText(/Rp\s*50\.000/);

    const paymentHistoryCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Riwayat pembayaran' });

    await expect(
        paymentHistoryCard.getByText('Pembayaran sewa', { exact: true }),
    ).toBeVisible();
    await expect(paymentHistoryCard).toContainText(golden.payment_method.name);

    await page.getByRole('button', { name: 'Konfirmasi' }).click();

    await expect(page.getByText('confirmed', { exact: true })).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Checkout booking' }),
    ).toBeVisible();
});
