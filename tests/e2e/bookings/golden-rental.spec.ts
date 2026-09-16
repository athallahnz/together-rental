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
    await trigger.evaluate((element) => {
        element.scrollIntoView({ block: 'center', inline: 'nearest' });
    });
    await expect(trigger).toBeVisible();
    await trigger.click();

    const option = page.getByRole('option', {
        name: optionName,
        exact: true,
    });

    await expect(option).toBeVisible();
    await option.click();
    await expect(trigger).toContainText(optionName);
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

test('golden rental: customer through extension payment', async ({ page }) => {
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
    const checkoutLink = page.getByRole('link', { name: 'Checkout booking' });

    await expect(checkoutLink).toBeVisible();
    await checkoutLink.click();

    await expect(page).toHaveURL(/\/rentals\/checkout\/\d+$/);
    await expect(
        page.getByRole('heading', { name: 'Checkout booking', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText(golden.asset.code, { exact: true }),
    ).toBeVisible();

    await page
        .getByPlaceholder('Kelengkapan/catatan unit')
        .fill(golden.checkout.asset_notes);
    await page.getByRole('button', { name: 'Tambah jaminan fisik' }).click();

    const collateralCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Jaminan fisik / dokumen' });

    await collateralCard
        .getByPlaceholder('NIK / nomor SIM / nomor kartu')
        .fill(golden.checkout.collateral.number);
    await collateralCard
        .getByPlaceholder('Nama pemilik jaminan')
        .fill(golden.checkout.collateral.holder_name);
    await collateralCard
        .getByPlaceholder(
            'Kondisi fisik, tempat penyimpanan, atau catatan lain',
        )
        .fill(golden.checkout.collateral.notes);
    await page
        .getByPlaceholder('Catatan umum dan kelengkapan yang dibawa.')
        .fill(golden.checkout.notes);

    await page
        .getByRole('button', { name: 'Checkout menjadi rental aktif' })
        .click();

    await expect(page).toHaveURL(/\/rentals\/\d+$/);

    const rentalTitleRow = page.locator('h1').locator('..');

    await expect(
        rentalTitleRow.getByText('active', { exact: true }),
    ).toBeVisible();

    const unitCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Unit yang dibawa' });

    await expect(unitCard).toContainText(golden.asset.code);
    await expect(unitCard).toContainText(golden.checkout.asset_notes);

    const heldCollateralCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Jaminan fisik / dokumen' });

    await expect(heldCollateralCard).toContainText(
        `${golden.checkout.collateral.type} · ${golden.checkout.collateral.number}`,
    );
    await expect(heldCollateralCard).toContainText('Ditahan');
    await expect(heldCollateralCard).toContainText(
        golden.checkout.collateral.holder_name,
    );
    await expect(page.getByRole('link', { name: 'Perpanjang' })).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Proses pengembalian' }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Perpanjang' }).click();

    await expect(page).toHaveURL(/\/rentals\/\d+\/extend$/);
    await expect(
        page.getByRole('heading', {
            name: 'Perpanjangan rental',
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByText(golden.asset.code, { exact: false }),
    ).toBeVisible();
    await page
        .getByLabel('Jumlah unit durasi')
        .fill(String(golden.extension.duration_units));

    const extensionPaymentCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Pembayaran opsional' });

    await extensionPaymentCard
        .getByPlaceholder('Bayar biaya perpanjangan')
        .fill(String(golden.extension.payment_amount));
    await selectOption(
        page,
        extensionPaymentCard.getByRole('combobox'),
        golden.payment_method.name,
    );
    await extensionPaymentCard
        .getByLabel('Referensi pembayaran')
        .fill(golden.extension.payment_reference);
    await page.getByLabel('Catatan perpanjangan').fill(golden.extension.notes);
    await page
        .getByLabel('Catatan pembayaran')
        .fill(golden.extension.payment_notes);
    await page.getByRole('button', { name: 'Setujui perpanjangan' }).click();

    await expect(page).toHaveURL(/\/rentals\/\d+$/);
    await expect(
        page.locator('h1').locator('..').getByText('active', { exact: true }),
    ).toBeVisible();

    const extensionHistoryCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Riwayat perpanjangan' });

    await expect(extensionHistoryCard).toContainText(golden.extension.notes);
    await expect(extensionHistoryCard).toContainText(/Rp\s*150\.000/);
    await expect(extensionHistoryCard).toContainText(/Dibayar\s*Rp\s*150\.000/);

    const rentalPaymentCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Pembayaran' });
    const paidRow = rentalPaymentCard
        .locator('p')
        .filter({ hasText: 'Dibayar' });
    const balanceRow = rentalPaymentCard
        .locator('p')
        .filter({ hasText: 'Sisa' });

    await expect(paidRow).toContainText(/Rp\s*200\.000/);
    await expect(balanceRow).toContainText(/Rp\s*100\.000/);

    await page.goto('/finance/payments');
    await expect(
        page.getByRole('heading', { name: 'Payment Center', exact: true }),
    ).toBeVisible();
    await page
        .getByPlaceholder('Payment, booking, rental, pelanggan, referensi...')
        .fill(golden.extension.payment_reference);
    await page.getByRole('button', { name: 'Cari', exact: true }).click();

    const paymentRow = page
        .locator('tbody tr')
        .filter({ hasText: golden.customer.name });

    await expect(paymentRow).toHaveCount(1);
    await expect(paymentRow).toContainText('Perpanjangan Rental');
    await expect(paymentRow).toContainText(golden.payment_method.name);
    await expect(paymentRow).toContainText('Completed');
    await expect(paymentRow).toContainText(/Rp\s*150\.000/);
    await paymentRow.getByRole('link').first().click();

    await expect(page).toHaveURL(/\/finance\/payments\/\d+$/);

    const paymentDetailCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Detail Payment' });
    const paymentSourceCard = page
        .locator('[data-slot="card"]')
        .filter({ hasText: 'Sumber Transaksi' });

    await expect(paymentDetailCard).toContainText(/Rp\s*150\.000/);
    await expect(paymentDetailCard).toContainText(golden.payment_method.name);
    await expect(paymentDetailCard).toContainText(
        golden.extension.payment_reference,
    );
    await expect(paymentDetailCard).toContainText(
        golden.extension.payment_notes,
    );
    await expect(paymentSourceCard).toContainText('Perpanjangan Rental');
});
