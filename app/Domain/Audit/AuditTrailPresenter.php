<?php

namespace App\Domain\Audit;

use App\Models\Booking;
use App\Models\BranchTransfer;
use App\Models\Customer;
use App\Models\InventoryAudit;
use App\Models\MaintenanceOrder;
use App\Models\OperationalExpense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\TransactionDocument;
use App\Models\User;
use stdClass;

class AuditTrailPresenter
{
    /** @return array<string, mixed> */
    public function present(stdClass $row, User $actor): array
    {
        $oldValues = $this->decode($row->old_values ?? null);
        $newValues = $this->decode($row->new_values ?? null);

        return [
            'id' => (int) $row->id,
            'event' => (string) $row->event,
            'event_label' => $this->humanize((string) $row->event),
            'module' => $this->module((string) $row->event),
            'module_label' => $this->humanize($this->module((string) $row->event)),
            'description' => $row->description,
            'actor' => $row->actor_id === null ? null : [
                'id' => (int) $row->actor_id,
                'name' => (string) ($row->actor_name ?? 'Pengguna terhapus'),
                'email' => $row->actor_email,
            ],
            'branch' => $row->branch_id === null ? null : [
                'id' => (int) $row->branch_id,
                'code' => (string) ($row->branch_code ?? '-'),
                'name' => (string) ($row->branch_name ?? 'Cabang terhapus'),
            ],
            'subject' => [
                'type' => $row->subject_type,
                'label' => $this->subjectLabel($row->subject_type),
                'id' => $row->subject_id === null ? null : (int) $row->subject_id,
                'url' => $this->subjectUrl($row->subject_type, $row->subject_id, $actor),
            ],
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changes' => $this->changes($oldValues, $newValues),
            'ip_address' => $row->ip_address,
            'user_agent' => $row->user_agent,
            'request_id' => $row->request_id,
            'created_at' => (string) $row->created_at,
        ];
    }

    public function module(string $event): string
    {
        $parts = explode('.', $event, 2);

        return $parts[0] === '' ? 'other' : $parts[0];
    }

    /** @return array<string, mixed>|null */
    private function decode(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $this->redact($decoded);
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($this->sensitiveKey((string) $key)) {
                $values[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    private function sensitiveKey(string $key): bool
    {
        return preg_match('/password|secret|token|recovery|remember|private.*path|file.*path|proof.*path|media.*path|document.*path/i', $key) === 1;
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @return list<array{key: string, label: string, old: mixed, new: mixed}>
     */
    private function changes(?array $oldValues, ?array $newValues): array
    {
        if ($oldValues === null && $newValues === null) {
            return [];
        }

        $old = $oldValues ?? [];
        $new = $newValues ?? [];
        $keys = array_values(array_unique([...array_keys($old), ...array_keys($new)]));
        $changes = [];

        foreach ($keys as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            if ($this->comparable($before) === $this->comparable($after)) {
                continue;
            }

            $changes[] = [
                'key' => (string) $key,
                'label' => $this->humanize((string) $key),
                'old' => $before,
                'new' => $after,
            ];
        }

        return $changes;
    }

    private function comparable(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function humanize(string $value): string
    {
        return str($value)
            ->replace(['.', '_', '-'], ' ')
            ->squish()
            ->title()
            ->toString();
    }

    private function subjectLabel(?string $subjectType): string
    {
        if ($subjectType === null || $subjectType === '') {
            return 'System';
        }

        return $this->humanize(class_basename($subjectType));
    }

    private function subjectUrl(?string $subjectType, mixed $subjectId, User $actor): ?string
    {
        if ($subjectType === null || $subjectId === null) {
            return null;
        }

        $id = (int) $subjectId;

        return match ($subjectType) {
            Booking::class => $actor->can('bookings.view') ? "/bookings/{$id}" : null,
            Rental::class => $actor->can('rentals.view') ? "/rentals/{$id}" : null,
            Payment::class => $actor->can('payments.view') ? "/finance/payments/{$id}" : null,
            Refund::class => $actor->can('refunds.view') ? "/finance/refunds/{$id}" : null,
            OperationalExpense::class => $actor->can('expenses.view') ? "/finance/expenses/{$id}" : null,
            TransactionDocument::class => $actor->can('documents.view') ? '/documents' : null,
            BranchTransfer::class => $actor->can('transfers.view') ? "/transfers/{$id}" : null,
            MaintenanceOrder::class => $actor->can('maintenance.view') ? "/maintenance/{$id}" : null,
            InventoryAudit::class => $actor->can('inventory-audits.view') ? "/inventory-audits/{$id}" : null,
            Customer::class => $actor->can('customers.view') ? "/customers/{$id}" : null,
            Product::class => $actor->can('products.view') ? "/catalog/products/{$id}" : null,
            default => null,
        };
    }
}
