<?php

namespace App\Domain\Rentals;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DirectRentalQuote
{
    /** @param array<string, mixed> $quote */
    public function issue(array $quote, User $actor): string
    {
        return Crypt::encryptString(json_encode([
            'actor' => $actor->id, 'expires' => now()->addMinutes(20)->timestamp,
            'hash' => $this->fingerprint($quote),
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $quote */
    public function assertCurrent(string $token, array $quote, User $actor): void
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $payload = null;
        }
        $reason = match (true) {
            ! is_array($payload), ! is_string($payload['hash'] ?? null) => 'invalid_token',
            ($payload['actor'] ?? null) !== $actor->id => 'actor_mismatch',
            (int) ($payload['expires'] ?? 0) < now()->timestamp => 'expired',
            ! hash_equals($payload['hash'], $this->fingerprint($quote)) => 'quote_changed',
            default => null,
        };
        if ($reason !== null) {
            Log::notice('Direct rental quote rejected', ['reason' => $reason, 'branch_id' => $quote['branch_id'] ?? null]);
            throw ValidationException::withMessages(['quote_token' => __('direct_rental.quote_changed')]);
        }
    }

    /** @param array<string, mixed> $quote */
    private function fingerprint(array $quote): string
    {
        return hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
