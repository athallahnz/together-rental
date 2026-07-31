<?php

namespace App\Domain\Transfers;

use App\Domain\Transfers\Enums\CaptureMode;
use Illuminate\Support\Facades\DB;

class TransferSettings
{
    /** @return array{capture_mode: string, min_photos: int, require_waybill: bool, allow_gallery_override: bool} */
    public function dispatch(int $branchId): array
    {
        return [
            'capture_mode' => $this->string($branchId, 'transfer_dispatch_capture_mode', CaptureMode::CameraRequired->value),
            'min_photos' => $this->integer($branchId, 'transfer_dispatch_min_photos', 1),
            'require_waybill' => $this->boolean($branchId, 'transfer_require_waybill', true),
            'allow_gallery_override' => $this->boolean($branchId, 'transfer_allow_gallery_override', false),
        ];
    }

    /** @return array{capture_mode: string, min_photos: int, require_waybill: bool, allow_gallery_override: bool} */
    public function receiving(int $branchId): array
    {
        return [
            'capture_mode' => $this->string($branchId, 'transfer_receiving_capture_mode', CaptureMode::CameraRequired->value),
            'min_photos' => $this->integer($branchId, 'transfer_receiving_min_photos', 1),
            'require_waybill' => false,
            'allow_gallery_override' => $this->boolean($branchId, 'transfer_allow_gallery_override', false),
        ];
    }

    /** @return array<string, mixed> */
    public function all(int $branchId): array
    {
        return [
            'dispatch_capture_mode' => $this->string($branchId, 'transfer_dispatch_capture_mode', CaptureMode::CameraRequired->value),
            'receiving_capture_mode' => $this->string($branchId, 'transfer_receiving_capture_mode', CaptureMode::CameraRequired->value),
            'dispatch_min_photos' => $this->integer($branchId, 'transfer_dispatch_min_photos', 1),
            'receiving_min_photos' => $this->integer($branchId, 'transfer_receiving_min_photos', 1),
            'require_waybill' => $this->boolean($branchId, 'transfer_require_waybill', true),
            'allow_gallery_override' => $this->boolean($branchId, 'transfer_allow_gallery_override', false),
        ];
    }

    /** @param array<string, mixed> $data */
    public function update(int $branchId, array $data): void
    {
        $settings = [
            'transfer_dispatch_capture_mode' => ['string', $data['dispatch_capture_mode']],
            'transfer_receiving_capture_mode' => ['string', $data['receiving_capture_mode']],
            'transfer_dispatch_min_photos' => ['integer', (int) $data['dispatch_min_photos']],
            'transfer_receiving_min_photos' => ['integer', (int) $data['receiving_min_photos']],
            'transfer_require_waybill' => ['boolean', (bool) $data['require_waybill']],
            'transfer_allow_gallery_override' => ['boolean', (bool) $data['allow_gallery_override']],
        ];

        foreach ($settings as $key => [$type, $value]) {
            DB::table('branch_settings')->updateOrInsert(
                ['branch_id' => $branchId, 'key' => $key],
                [
                    'value_type' => $type,
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'is_public' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function raw(int $branchId, string $key, mixed $default): mixed
    {
        $value = DB::table('branch_settings')
            ->where('branch_id', $branchId)
            ->where('key', $key)
            ->value('value');

        if ($value === null) {
            return $default;
        }

        try {
            return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $default;
        }
    }

    private function string(int $branchId, string $key, string $default): string
    {
        $value = $this->raw($branchId, $key, $default);

        return is_string($value) ? $value : $default;
    }

    private function integer(int $branchId, string $key, int $default): int
    {
        return max(0, (int) $this->raw($branchId, $key, $default));
    }

    private function boolean(int $branchId, string $key, bool $default): bool
    {
        return (bool) $this->raw($branchId, $key, $default);
    }
}
