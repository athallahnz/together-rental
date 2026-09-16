# Playwright E2E — Internal UAT

Suite ini menjalankan browser Chromium terhadap Laravel sungguhan dengan database MySQL/MariaDB lokal yang didedikasikan untuk E2E.

## Safety contract

`e2e:prepare` akan menolak reset kecuali seluruh syarat ini terpenuhi:

- environment Laravel adalah `e2e`;
- `E2E_ALLOW_DATABASE_RESET=true`;
- connection adalah `mysql` atau `mariadb`;
- nama database berakhiran `_e2e`;
- host database adalah `127.0.0.1`, `localhost`, atau `::1`;
- opsi `--force` diberikan.

Jangan arahkan `.env.e2e` ke database development atau production.

## Initial setup

Jalankan dari root project:

```powershell
.\scripts\e2e\Configure-E2EEnvironment.ps1
```

Script membuat `.env.e2e` dari `.env` lokal, mengganti environment/URL/nama database, dan mempertahankan port, username, serta password database lokal. Periksa file hasilnya sebelum melanjutkan.

Buat database kosong melalui phpMyAdmin atau client database:

```sql
CREATE DATABASE together_rental_e2e
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Install browser Chromium satu kali:

```powershell
npm run e2e:install
```

## Run

Persiapkan ulang database saja:

```powershell
npm run e2e:prepare
```

Jalankan POC headless:

```powershell
npm run e2e:test
```

Mode headed atau UI:

```powershell
npm run e2e:test:headed
npm run e2e:ui
```

Laporan terakhir:

```powershell
npm run e2e:report
```

Trace, screenshot, video, dan HTML report disimpan di `storage/framework/testing/` dan hanya dipertahankan untuk failure sesuai konfigurasi.

## POC coverage

- `UAT-001`: invalid login, valid login, dan logout.
- `UAT-002`: user terbatas menerima HTTP 403 pada Branch Management; super-admin dapat membukanya.
- `UAT-003`: branch manager hanya melihat dashboard Ponorogo dan menerima HTTP 403 saat meminta scope Madiun.

## Golden Rental Journey — tahap pertama

`tests/e2e/bookings/golden-rental.spec.ts` melanjutkan POC dengan satu perjalanan browser yang saling terhubung:

- membuat pelanggan baru dari Customer Center;
- memeriksa ketersediaan unit menggunakan session browser terautentikasi;
- membuat booking draft dan memastikan unit terreservasi;
- mencatat DP sewa melalui detail booking;
- mengonfirmasi booking dan memastikan aksi checkout tersedia.

Seeder menyediakan produk, tarif, aset, metode pembayaran transfer, serta metadata jadwal khusus untuk perjalanan ini. Customer dan booking tetap dibuat melalui UI agar test menguji alur pengguna nyata.

## Golden Rental Journey — tahap kedua

Journey yang sama dilanjutkan tanpa membuat transaksi sintetis langsung di database:

- membuka checkout dari booking yang sudah dikonfirmasi;
- memverifikasi unit dan mencatat kelengkapannya;
- menerima jaminan fisik KTP melalui form checkout;
- mengaktifkan rental dan memastikan aset tampil sebagai unit yang dibawa;
- memastikan jaminan berstatus `Ditahan`;
- memastikan aksi perpanjangan dan pengembalian tersedia pada rental aktif.

Pembayaran tambahan sengaja tidak dicatat pada tahap ini agar urutan journey tetap sesuai UAT: DP sebelum konfirmasi, checkout dan collateral, kemudian extension dan pelunasan pada tahap berikutnya.

## Golden Rental Journey — tahap ketiga

Journey dilanjutkan dari rental aktif ke perpanjangan dan ledger pembayaran:

- membuka form perpanjangan dari detail rental;
- memperpanjang seluruh unit yang masih keluar selama satu hari;
- mencatat pembayaran perpanjangan melalui Bank Transfer;
- memverifikasi riwayat perpanjangan, total dibayar, dan saldo rental;
- mencari pembayaran melalui referensi unik di Payment Center;
- membuka Detail Payment dan memverifikasi nominal, metode, referensi, catatan, serta source `Perpanjangan Rental`.

Tahap ini menguji integrasi browser antara rental extension dan payment ledger tanpa membuat extension atau payment secara langsung melalui seeder.

Suite ini bukan pengganti manual internal UAT maupun client acceptance.

## Gates

```powershell
herd php artisan test tests/Feature/E2E/E2EEnvironmentSafetyTest.php

herd php vendor/bin/phpstan analyse `
  app/Console/Commands/PrepareE2EEnvironment.php `
  database/seeders/E2EUatSeeder.php `
  --memory-limit=1G

npm run types:check
npm run e2e:types:check
npm run lint:check
npm run format:check
npm run e2e:format:check
npm run build
npm run e2e:test
```

Setelah targeted gates hijau berdasarkan output terminal aktual, lanjutkan full PHPUnit regression sebelum Git checkpoint.
