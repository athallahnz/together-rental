<?php

namespace App\Domain\Finance;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentEvidenceStorage
{
    /**
     * Store once for a request, including split rental/deposit payments.
     * Delete the file if the business transaction fails before commit.
     *
     * @template T
     *
     * @param  array<string, mixed>  $payload
     * @param  Closure(array<string, mixed>): T  $action
     * @return T
     */
    public function run(array $payload, Closure $action): mixed
    {
        $proof = $payload['payment_proof'] ?? null;
        unset($payload['payment_proof']);
        if (! $proof instanceof UploadedFile) {
            return $action($payload);
        }

        $path = $proof->store('finance/payments/'.now()->format('Y/m'), 'local');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'payment_proof' => 'Bukti pembayaran gagal disimpan.',
            ]);
        }

        $payload['proof_path'] = $path;

        try {
            return $action($payload);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}
