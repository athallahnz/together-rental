# Apply PNG Dynamic Branch Patch

This patch removes the hardcoded `PON` branch identifier from the RentalV1
import pipeline. The configured target branch defaults to `PNG`, while customer
numbers, asset codes, mapping labels, and document sequence prefixes follow the
actual branch code.

## Environment

Add these values to `.env`:

```dotenv
INITIAL_BRANCH_CODE=PNG
LEGACY_IMPORT_BRANCH_CODE=PNG
```

## Apply

Stop the development processes, replace the patched files, and run:

```powershell
herd php artisan optimize:clear
herd php artisan db:seed --class=RentalFoundationSeeder
herd php artisan test --filter=Rental
npm run build
```

The seeder is idempotent. It updates the existing `PNG` branch defaults and
document prefixes without recreating the old `PON` branch.

## Duplicate branch

Do not delete the duplicate branch immediately. First confirm that no
operational or access-control foreign keys still reference it. The old branch
can be deactivated or removed only after that audit passes.
