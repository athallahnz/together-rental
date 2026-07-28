<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PublicCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = DB::table('branches')
            ->where('code', 'PNG')
            ->whereNull('deleted_at')
            ->value('id');

        if ($branchId === null) {
            return;
        }

        $now = now();
        $settings = [
            'public_catalog_enabled' => ['boolean', true],
            'public_whatsapp' => ['string', '6285784771927'],
            'public_maps_url' => [
                'string',
                'https://www.google.com/maps/place/Together+Kamera+Store+%2F+Toko+Kamera+Ponrogo+(+JUAL+BELI+KAMERA+BARU+%26+BEKAS+,+SEWA+,+JUAL+BELI+,GADAI+%26+SERVIS+)/@-7.8503838,111.4929575,19.22z/data=!4m15!1m8!3m7!1s0x2e790b859cfee851:0x3027a76e352bea0!2sPonorogo+Regency,+East+Java!3b1!8m2!3d-7.8650759!4d111.4696322!16zL20vMGdjN21w!3m5!1s0x2e79a1d81bfb0687:0x36ff0cdf9787eba7!8m2!3d-7.8499244!4d111.4931516!16s%2Fg%2F11gy6371h1?hl=en&entry=ttu&g_ep=EgoyMDI2MDcyMi4wIKXMDSoASAFQAw%3D%3D',
            ],
            'public_instagram' => ['string', 'together_kamera'],
            'public_opening_hours' => ['string', '09.00–21.00 WIB'],
            'public_short_address' => [
                'string',
                'Jl. Brigjend Katamso Gg. VI No. 5, Tengah, Kadipaten, Kec. Babadan, Kabupaten Ponorogo, Jawa Timur 63491',
            ],
            'public_logo_path' => ['string', '/primary-logos.png'],
            'public_hero_title' => ['string', 'Sewa alat kreatif tanpa ribet.'],
            'public_hero_description' => [
                'string',
                'Temukan kamera, lensa, lighting, audio, dan perlengkapan produksi yang siap menemani karya Anda.',
            ],
        ];

        foreach ($settings as $key => [$type, $value]) {
            DB::table('branch_settings')->updateOrInsert(
                ['branch_id' => $branchId, 'key' => $key],
                [
                    'value_type' => $type,
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'is_public' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
