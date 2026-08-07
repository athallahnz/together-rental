<?php

namespace App\Domain\LegacyImport;

final class RentalV1Normalizer
{
    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sanitize(string $table, array $payload): array
    {
        if ($table === 'user') {
            unset($payload['pwd'], $payload['api_token']);
        }

        if ($table === 'karyawan') {
            unset($payload['img']);
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function normalize(string $table, array $payload, string $importPrefix): array
    {
        $prefix = strtoupper(trim($importPrefix));

        if (! preg_match('/^[A-Z]{3}$/', $prefix)) {
            throw new \InvalidArgumentException('PREFIX import wajib tepat 3 huruf A-Z.');
        }

        return match ($table) {
            'customer' => [
                'customer_number' => "LEG-{$prefix}-".RentalV1Value::integer($payload['customer_id'] ?? null),
                'name' => RentalV1Value::string($payload['customer_name'], 150),
                'gender' => RentalV1Value::gender($payload['customer_jeniskelamin'] ?? null),
                'phone' => RentalV1Value::string($payload['customer_nohp'] ?? null, 30),
                'identity_type' => RentalV1Value::identityType($payload['customer_type_id'] ?? null),
                'identity_number' => RentalV1Value::string($payload['customer_noid'] ?? null, 80),
                'birth_place' => RentalV1Value::string($payload['customer_birth_place'] ?? null, 100),
                'birth_date' => RentalV1Value::date($payload['customer_birth_day'] ?? null),
                'institution' => RentalV1Value::string($payload['customer_instansi'] ?? null, 150),
                'is_member' => RentalV1Value::boolean($payload['customer_is_member'] ?? null),
                'member_number' => RentalV1Value::string($payload['customer_nomember'] ?? null, 40),
            ],
            'rent_product' => [
                'sku' => $this->prefixedCode(
                    RentalV1Value::string($payload['rentproduct_code'] ?? null, 50)
                        ?? 'LEG-PRD-'.RentalV1Value::integer($payload['rentproduct_id'] ?? null),
                    $prefix,
                    50,
                ),
                'name' => RentalV1Value::string($payload['rentproduct_name'] ?? null, 150),
                'serial_number' => RentalV1Value::string($payload['rentproduct_serial_number'] ?? null, 120),
                'tracking_type' => RentalV1Value::boolean($payload['rentproduct_is_serial'] ?? null)
                    ? 'serialized'
                    : 'quantity',
                'asset_status' => RentalV1Value::productStatus($payload['rentproduct_status'] ?? null),
                'is_active' => RentalV1Value::boolean($payload['rentproduct_is_active'] ?? null),
            ],
            'trx_booking' => [
                'number' => RentalV1Value::string($payload['booking_number'] ?? null, 50),
                'status' => RentalV1Value::bookingStatus($payload['booking_status'] ?? null),
                'booked_at' => RentalV1Value::dateTime($payload['booking_date'] ?? null),
                'starts_at' => RentalV1Value::dateTime($payload['booking_date_start'] ?? null),
                'ends_at' => RentalV1Value::dateTime($payload['booking_date_end'] ?? null),
                'total_amount' => RentalV1Value::decimal($payload['booking_total_akhir'] ?? null),
            ],
            'trx_rental' => [
                'number' => RentalV1Value::string($payload['rental_number'] ?? null, 50),
                'status' => RentalV1Value::rentalStatus($payload['rental_status'] ?? null),
                'checked_out_at' => RentalV1Value::dateTime($payload['rental_date_start'] ?? null),
                'due_at' => RentalV1Value::dateTime($payload['rental_date_end'] ?? null),
                'returned_at' => RentalV1Value::dateTime($payload['rental_date_kembali'] ?? null),
                'total_amount' => RentalV1Value::decimal($payload['rental_total_akhir'] ?? null),
                'paid_amount' => RentalV1Value::decimal($payload['rental_bayar'] ?? null),
            ],
            default => $this->normalizeScalars($payload),
        };
    }

    private function prefixedCode(string $value, string $prefix, int $maxLength): string
    {
        $value = strtoupper(trim($value));

        if (str_starts_with($value, $prefix.'-')) {
            return mb_substr($value, 0, $maxLength);
        }

        return mb_substr($prefix.'-'.$value, 0, $maxLength);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeScalars(array $payload): array
    {
        return array_map(
            static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
            $payload,
        );
    }
}
