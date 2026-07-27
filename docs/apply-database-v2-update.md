# Applying the Database V2 Foundation Update

These steps upgrade an existing Together Rental installation without deleting
the user account that has already been created.

## Before updating

1. Stop `composer run dev`.
2. Back up the current project and MySQL database.
3. Confirm that `php -v` reports PHP 8.4.1 or newer.
4. Extract the patch archive into the existing `together-rental` directory and
   allow it to overwrite matching source files.

Do not run `migrate:fresh`; it drops all existing tables and data.

## Apply the update

Run these commands from the project directory:

```powershell
herd composer install
herd php artisan optimize:clear
herd php artisan migrate --seed
herd php artisan rental:bootstrap-admin YOUR_LOGIN_EMAIL
npm install
npm run build
herd php artisan test
```

Replace `YOUR_LOGIN_EMAIL` with the email address of the account that can
already log in. The bootstrap command:

- links the account to Together Kamera;
- gives it access to the `PNG` branch;
- sets `PNG` as its active branch; and
- assigns the company-level `Super Admin` role.

Both the foundation seeder and administrator bootstrap command are idempotent,
so they are safe to run again.

To display the email addresses currently registered in the application:

```powershell
herd php artisan tinker --execute="dump(App\Models\User::query()->pluck('email')->all());"
```

## Windows and Laravel Herd

The Wayfinder Vite plugin and Laravel's `server` and `queue` development
processes use `herd php` automatically on Windows. This prevents an older XAMPP
PHP executable from being selected during `npm run build` or
`herd composer run dev`. Confirm the active project PHP and development
commands before starting:

```powershell
herd php -v
herd which-php
herd php artisan dev:list
```

The `server` and `queue` rows from `dev:list` should both start with
`herd php artisan`.

If a migration previously stopped part-way through, replace the corrected
migration files and run `herd php artisan migrate --seed` again. Laravel skips
the completed migration files and resumes from the first failed file.

## Scope of this update

This update installs the normalized multi-branch schema, Ponorogo defaults,
roles and permissions, legacy import staging/audit tables, and the complete
RentalV1 import workflow:

1. Upload SQL
2. Preview
3. Validation
4. Mapping cabang PNG
5. Execute
6. Verification

The update also adds the private source-file path to import batches and scopes
legacy ID maps by branch.

After applying the update, start development mode and keep the terminal open:

```powershell
herd composer run dev
```

The queue process shown in that terminal performs the long-running preview,
validation, execute, and verification steps. Open **Legacy Import** in the
sidebar after signing in.

Before pressing **Execute**, make a MySQL backup. Do not use `migrate:fresh`.
