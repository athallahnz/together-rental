# RentalV1 Legacy Import

## Source

The first legacy source is the RentalV1 desktop client-server database from
Together Kamera Ponorogo. Every imported operational record is assigned to the
`PNG` branch.

Observed source volumes:

| Entity | Rows |
| --- | ---: |
| Customers | 4,788 |
| Positions / employees | 4 / 8 |
| Rental products | 442 |
| Product stock | 442 |
| Bookings | 236 |
| Booking items | 473 |
| Rentals | 10,382 |
| Rental items | 17,295 |
| Extensions | 424 |
| Extension items | 620 |
| Collaterals | 10,411 |

The allowlist parser stages 46,043 source rows from the supplied Ponorogo dump.
Reference and audit-only tables are included in that number.

## Pipeline

1. **Upload** — store the source file privately and calculate SHA-256.
2. **Preview** — parse only allowlisted legacy tables; never execute uploaded SQL.
3. **Validation** — detect duplicates, invalid references, incomplete assets,
   financial mismatches, and unresolved active transactions.
4. **Mapping** — confirm branch, customer deduplication, product/asset split,
   statuses, employees, rates, and collateral types.
5. **Execute** — import in dependency order using transactions and chunks.
6. **Verification** — reconcile row counts, amounts, active rentals, and ID maps.

Preview, validation, execute, and verification run as queued jobs. The Laravel
queue worker must stay active while those steps are in progress. Mapping cabang is
a short synchronous confirmation.

## Import order

1. Product categories and employee positions
2. Customer profiles, identities, addresses, and employees
3. Products, assets, rates, and opening branch inventory
4. Bookings and booking items
5. Rentals, rental items, and assigned assets
6. Extensions and extension items
7. Returns and collaterals
8. Loyalty balances and transactions when present
9. Verification and batch lock

## Known validation queues

- Duplicate customer identity numbers
- Duplicate phone numbers
- Missing customer gender
- Missing or repeated asset serial numbers
- Products without a name
- Rentals whose recorded paid amount is below the recorded total
- Bookings still marked `pesan`
- Rentals still marked `pinjam`
- Extensions still marked `perpanjangan`
- Non-standard collateral types

The supplied Ponorogo dump is expected to have no blocking reference errors.
One product has a blank name; it is reported as a warning and receives the
fallback name `Legacy Product {legacy_id}`. The dump also contains 53 open
bookings, 445 active rentals, and 24 active extensions. These remain warnings
because they are valid operational carry-over data.

## Security

- Legacy password hashes are never imported.
- Uploaded SQL is parsed as data and is never sent to the database engine.
- Identity numbers and documents are restricted to authorized roles.
- Every mapping decision and execute action is recorded in import events.
- Re-importing the same SHA-256 source is blocked.

## Idempotency

`legacy_id_maps` keeps the source system, table, and primary key associated with
the generated V2 record. The unique branch/source key prevents a legacy record
from being imported twice. Execute runs inside a database transaction, so a
failed execute can be retried without leaving a partially imported target set.

## Operating the import

Start the application from the project directory:

```powershell
herd composer run dev
```

Keep that terminal open because it runs the web server, queue worker, and Vite.
Then:

1. Sign in with the bootstrapped Super Admin account.
2. Open **Legacy Import** in the sidebar.
3. Upload the original `.sql` dump.
4. Run **Preview** and wait until the status changes to `Preview tersedia`.
5. Run **Validasi** and review errors and warnings.
6. Continue only when the blocking error count is zero.
7. Confirm **Mapping PNG**.
8. Back up the V2 database, then run **Execute**.
9. Run **Verifikasi** and confirm every reconciliation check passes.

Do not edit or execute the legacy SQL manually. Do not use `migrate:fresh` on a
database containing user accounts or imported data.

## Configuration

The optional `.env` settings are:

```dotenv
LEGACY_IMPORT_DISK=local
LEGACY_IMPORT_MAX_KB=102400
LEGACY_IMPORT_CHUNK_SIZE=500
LEGACY_IMPORT_BRANCH_CODE=PNG
```

The default `local` disk keeps uploads under Laravel's private application
storage, not under `public/`.
