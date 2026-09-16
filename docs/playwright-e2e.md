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
