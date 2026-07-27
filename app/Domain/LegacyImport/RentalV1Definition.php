<?php

namespace App\Domain\LegacyImport;

use InvalidArgumentException;

final class RentalV1Definition
{
    /**
     * Only these tables are read from the uploaded dump. Uploaded SQL is never executed.
     *
     * @var array<string, array{
     *     label: string,
     *     target: string|null,
     *     keys: list<string>,
     *     required?: list<string>,
     *     references?: array<string, array{0: string, 1: string}>,
     *     executable?: bool
     * }>
     */
    public const TABLES = [
        'customer' => [
            'label' => 'Pelanggan',
            'target' => 'customers',
            'keys' => ['customer_id'],
            'required' => ['customer_id', 'customer_name'],
        ],
        'customer_identity' => [
            'label' => 'Identitas tambahan pelanggan',
            'target' => 'customer_identities',
            'keys' => ['customer_id', 'customer_identity_type'],
            'required' => ['customer_id', 'customer_identity_type'],
            'references' => ['customer_id' => ['customer', 'customer_id']],
        ],
        'jabatan' => [
            'label' => 'Jabatan',
            'target' => 'positions',
            'keys' => ['idjabatan'],
            'required' => ['idjabatan', 'nama'],
        ],
        'karyawan' => [
            'label' => 'Karyawan',
            'target' => 'employees',
            'keys' => ['idkaryawan'],
            'required' => ['idkaryawan', 'nama'],
            'references' => ['idjabatan' => ['jabatan', 'idjabatan']],
        ],
        'lokasi' => [
            'label' => 'Referensi lokasi',
            'target' => null,
            'keys' => ['idlokasi'],
            'executable' => false,
        ],
        'package_rental' => [
            'label' => 'Paket rental',
            'target' => 'packages',
            'keys' => ['package_id'],
            'required' => ['package_id', 'package_name'],
        ],
        'package_rental_detail' => [
            'label' => 'Detail paket rental',
            'target' => 'package_items',
            'keys' => ['packagedet_package_id', 'packagedet_rentproduct_id'],
            'references' => [
                'packagedet_package_id' => ['package_rental', 'package_id'],
                'packagedet_rentproduct_id' => ['rent_product', 'rentproduct_id'],
            ],
        ],
        'point_member' => [
            'label' => 'Saldo poin pelanggan',
            'target' => 'loyalty_accounts',
            'keys' => ['point_customer_id'],
            'references' => ['point_customer_id' => ['customer', 'customer_id']],
        ],
        'point_transaction' => [
            'label' => 'Transaksi poin',
            'target' => 'loyalty_transactions',
            'keys' => ['pointtrx_rental_id', 'pointtrx_status'],
            'references' => [
                'pointtrx_customer_id' => ['customer', 'customer_id'],
                'pointtrx_rental_id' => ['trx_rental', 'rental_id'],
            ],
        ],
        'promo' => [
            'label' => 'Promo',
            'target' => 'promotions',
            'keys' => ['promo_id'],
            'required' => ['promo_id', 'promo_name'],
        ],
        'ref_category_product' => [
            'label' => 'Kategori produk',
            'target' => 'product_categories',
            'keys' => ['category_id'],
            'required' => ['category_id', 'category_name'],
        ],
        'ref_jaminantype_id' => [
            'label' => 'Referensi jenis jaminan',
            'target' => null,
            'keys' => ['jaminantype_id'],
            'executable' => false,
        ],
        'rent_product' => [
            'label' => 'Produk dan aset',
            'target' => 'products',
            'keys' => ['rentproduct_id'],
            'required' => ['rentproduct_id'],
            'references' => [
                'rentproduct_category_id' => ['ref_category_product', 'category_id'],
            ],
        ],
        'rent_product_package' => [
            'label' => 'Komponen produk paket',
            'target' => 'package_items',
            'keys' => ['package_profile_rentproduct_id', 'package_single_rentproduct_id'],
            'references' => [
                'package_profile_rentproduct_id' => ['rent_product', 'rentproduct_id'],
                'package_single_rentproduct_id' => ['rent_product', 'rentproduct_id'],
            ],
        ],
        'rent_product_stock' => [
            'label' => 'Stok produk',
            'target' => 'branch_inventories',
            'keys' => ['rentstock_rentproduct_id'],
            'references' => [
                'rentstock_rentproduct_id' => ['rent_product', 'rentproduct_id'],
            ],
        ],
        'trx_booking' => [
            'label' => 'Booking',
            'target' => 'bookings',
            'keys' => ['booking_id'],
            'required' => ['booking_id', 'booking_number', 'booking_customer_id'],
            'references' => [
                'booking_customer_id' => ['customer', 'customer_id'],
                'booking_karyawan_id' => ['karyawan', 'idkaryawan'],
                'booking_promo_id' => ['promo', 'promo_id'],
                'booking_package_id' => ['package_rental', 'package_id'],
            ],
        ],
        'trx_booking_detail' => [
            'label' => 'Detail booking',
            'target' => 'booking_items',
            'keys' => ['bookingdet_booking_id', 'bookingdet_rentproduct_id'],
            'references' => [
                'bookingdet_booking_id' => ['trx_booking', 'booking_id'],
                'bookingdet_rentproduct_id' => ['rent_product', 'rentproduct_id'],
            ],
        ],
        'trx_rental' => [
            'label' => 'Rental',
            'target' => 'rentals',
            'keys' => ['rental_id'],
            'required' => ['rental_id', 'rental_number', 'rental_customer_id'],
            'references' => [
                'rental_booking_id' => ['trx_booking', 'booking_id'],
                'rental_customer_id' => ['customer', 'customer_id'],
                'rental_penjamin_customer_id' => ['customer', 'customer_id'],
                'rental_out_karyawan_id' => ['karyawan', 'idkaryawan'],
                'rental_in_karyawan_id' => ['karyawan', 'idkaryawan'],
                'rental_promo_id' => ['promo', 'promo_id'],
                'rental_package_id' => ['package_rental', 'package_id'],
            ],
        ],
        'trx_rental_detail' => [
            'label' => 'Detail rental',
            'target' => 'rental_items',
            'keys' => ['rentaldet_rental_id', 'rentaldet_rentproduct_id'],
            'references' => [
                'rentaldet_rental_id' => ['trx_rental', 'rental_id'],
                'rentaldet_rentproduct_id' => ['rent_product', 'rentproduct_id'],
            ],
        ],
        'trx_extrarental' => [
            'label' => 'Perpanjangan rental',
            'target' => 'rental_extensions',
            'keys' => ['extrarental_id'],
            'required' => ['extrarental_id', 'extrarental_number', 'extrarental_rental_id'],
            'references' => [
                'extrarental_rental_id' => ['trx_rental', 'rental_id'],
                'extrarental_customer_id' => ['customer', 'customer_id'],
                'extrarental_penjamin_customer_id' => ['customer', 'customer_id'],
                'extrarental_out_karyawan_id' => ['karyawan', 'idkaryawan'],
                'extrarental_in_karyawan_id' => ['karyawan', 'idkaryawan'],
                'extrarental_promo_id' => ['promo', 'promo_id'],
                'extrarental_package_id' => ['package_rental', 'package_id'],
            ],
        ],
        'trx_extrarental_detail' => [
            'label' => 'Detail perpanjangan',
            'target' => 'rental_extension_items',
            'keys' => ['extradet_extrarental_id', 'extradet_rentproduct_id'],
            'references' => [
                'extradet_extrarental_id' => ['trx_extrarental', 'extrarental_id'],
                'extradet_rentproduct_id' => ['rent_product', 'rentproduct_id'],
            ],
        ],
        'trx_rental_jaminan' => [
            'label' => 'Jaminan rental',
            'target' => 'rental_collaterals',
            'keys' => ['rentaljaminan_rental_id', 'rentaljaminan_nourut'],
            'references' => [
                'rentaljaminan_rental_id' => ['trx_rental', 'rental_id'],
            ],
        ],
        'trx_rental_photo' => [
            'label' => 'Referensi foto rental',
            'target' => null,
            'keys' => ['rental_id'],
            'references' => ['rental_id' => ['trx_rental', 'rental_id']],
            'executable' => false,
        ],
        'trx_cashflow' => [
            'label' => 'Cashflow lama',
            'target' => null,
            'keys' => ['trx_id'],
            'executable' => false,
        ],
        'user' => [
            'label' => 'Pengguna lama (tanpa password)',
            'target' => null,
            'keys' => ['iduser'],
            'executable' => false,
        ],
        'user_level' => [
            'label' => 'Level pengguna lama',
            'target' => null,
            'keys' => ['idlevel'],
            'executable' => false,
        ],
    ];

    /** @return list<string> */
    public static function tables(): array
    {
        return array_keys(self::TABLES);
    }

    /** @return array<string, mixed> */
    public static function table(string $table): array
    {
        return self::TABLES[$table] ?? throw new InvalidArgumentException("Unsupported RentalV1 table [{$table}].");
    }

    /** @param array<string, mixed> $payload */
    public static function legacyKey(string $table, array $payload): string
    {
        $values = array_map(
            static fn (string $column): string => self::keyPart($payload[$column] ?? null),
            self::table($table)['keys'],
        );

        return implode('|', $values);
    }

    public static function isExecutable(string $table): bool
    {
        return (bool) (self::table($table)['executable'] ?? true);
    }

    private static function keyPart(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '__NULL__';
        }

        return str_replace(['\\', '|'], ['\\\\', '\\|'], (string) $value);
    }
}
