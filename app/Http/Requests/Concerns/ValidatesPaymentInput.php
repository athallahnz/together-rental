<?php

namespace App\Http\Requests\Concerns;

use App\Models\CashSession;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** @mixin FormRequest */
trait ValidatesPaymentInput
{
    protected function validatePaymentInput(
        Validator $validator,
        float $amount,
        int $branchId,
        bool $requireTransferProof = false,
    ): void {
        if ($amount <= 0) {
            if ($requireTransferProof && $this->hasFile('payment_proof')) {
                $validator->errors()->add('payment_proof', 'Bukti hanya dapat diunggah bersama pembayaran.');
            }

            return;
        }
        if (! $this->filled('payment_method_id')) {
            $validator->errors()->add('payment_method_id', 'Metode pembayaran wajib dipilih.');

            return;
        }

        $method = PaymentMethod::query()
            ->where('company_id', $this->user()->company_id)
            ->where('is_active', true)
            ->find($this->integer('payment_method_id'));
        if ($method === null) {
            $validator->errors()->add('payment_method_id', 'Metode pembayaran tidak tersedia.');

            return;
        }
        if ($requireTransferProof && in_array($method->type, ['bank_transfer', 'qris'], true) && ! $this->hasFile('payment_proof')) {
            $validator->errors()->add('payment_proof', 'Bukti transfer atau QRIS wajib diunggah.');
        } elseif ($method->requires_reference && (! $requireTransferProof || ! in_array($method->type, ['bank_transfer', 'qris'], true)) && ! $this->filled('payment_reference')) {
            $validator->errors()->add('payment_reference', 'Referensi pembayaran wajib diisi.');
        }

        if ($requireTransferProof && $this->hasFile('payment_proof') && ! in_array($method->type, ['bank_transfer', 'qris'], true)) {
            $validator->errors()->add('payment_proof', 'Bukti ini hanya untuk transfer atau QRIS.');
        }
        if ($method->type !== 'cash') {
            return;
        }
        if (! $this->filled('cash_session_id')) {
            $validator->errors()->add(
                'cash_session_id',
                'Sesi kas aktif wajib dipilih untuk pembayaran tunai.',
            );

            return;
        }

        $validSession = CashSession::query()
            ->whereKey($this->integer('cash_session_id'))
            ->where('status', 'open')
            ->whereHas('register', fn (Builder $query) => $query->where('branch_id', $branchId))
            ->exists();
        if (! $validSession) {
            $validator->errors()->add(
                'cash_session_id',
                'Sesi kas tidak aktif atau bukan milik cabang transaksi.',
            );
        }
    }
}
