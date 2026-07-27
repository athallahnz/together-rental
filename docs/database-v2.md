# Rental Management V2 Database Foundation

## Design goals

Rental Management V2 is a centralized, multi-branch system. Customers belong
to the company and can transact at any branch, while inventory, operational
transactions, cash sessions, numbering, and accountability remain explicitly
branch-scoped.

The legacy RentalV1 database is not the production schema. It is an immutable
source for a controlled import pipeline.

## Core invariants

- Every booking, rental, return, payment, cash session, maintenance order, and
  transfer has an explicit branch context.
- A physical serialized item is represented by an `asset`; a catalog model is
  represented by a `product`.
- The current asset branch and owning asset branch are stored separately.
- Asset availability is derived from reservations and operational state, not
  from a single global stock flag.
- Customer records are company-global and retain their registration branch.
- Historical transaction amounts are snapshots and must not be recalculated
  when future price lists change.
- Payments and refunds are ledger records. Financial history is not overwritten
  by editing rental totals.
- Partial returns are first-class records through `rental_returns` and
  `rental_return_items`.
- Cross-branch movement uses an auditable transfer workflow.
- Legacy primary keys never replace V2 primary keys.

## Domain groups

1. Organization and access: companies, branches, employees, roles, permissions.
2. Customers: profiles, identities, addresses, loyalty ledgers.
3. Catalog and inventory: products, assets, rates, packages, promotions.
4. Booking: bookings, line items, asset reservations, status history.
5. Rental lifecycle: checkout, assigned assets, extensions, partial/final return.
6. Asset care: inspections, evidence media, damage charges, maintenance.
7. Finance: payments, allocations, refunds, registers, sessions, cash ledger.
8. Multi-branch logistics: branch transfers and transfer items.
9. Governance: activity logs and generic status histories.
10. Legacy migration: batches, staging rows, mappings, issues, ID maps, events.

## Initial organization

- Company code: `TK`
- Company name: `Together Kamera`
- Initial branch code: `PNG`
- Initial branch name: `Together Kamera Ponorogo`
- Timezone: `Asia/Jakarta`
- Currency: `IDR`

Additional branches can be created without changing the schema.

## Status values

Status columns intentionally use indexed strings instead of database enums.
Application-level enums and validation will define allowed transitions. This
keeps future workflow changes deployable without destructive database changes.

## Numbering

`number_sequences` is scoped by branch, document type, year, and month. Number
allocation must be performed inside a database transaction with a row lock.

Examples:

- `PNG-BKG-202607-000001`
- `PNG-RNT-202607-000001`
- `PNG-PAY-202607-000001`
- `PNG-TRF-202607-000001`
