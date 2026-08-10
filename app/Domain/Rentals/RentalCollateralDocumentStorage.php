<?php

namespace App\Domain\Rentals;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RentalCollateralDocumentStorage
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, paths: list<string>}
     */
    public function prepareCheckoutPayload(array $payload): array
    {
        $raw = $payload['collaterals'] ?? [];
        if (! is_array($raw)) {
            return ['payload' => $payload, 'paths' => []];
        }

        /** @var list<array<string, mixed>> $collaterals */
        $collaterals = [];
        /** @var list<string> $paths */
        $paths = [];

        try {
            foreach ($raw as $input) {
                if (! is_array($input)) {
                    continue;
                }

                $prepared = $this->prepareOne($input);
                $collaterals[] = $prepared['payload'];
                if ($prepared['path'] !== null) {
                    $paths[] = $prepared['path'];
                }
            }
        } catch (\Throwable $exception) {
            $this->cleanup($paths);
            throw $exception;
        }

        $payload['collaterals'] = $collaterals;

        return ['payload' => $payload, 'paths' => $paths];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, path: string|null}
     */
    public function prepareOne(array $payload): array
    {
        $document = $payload['document'] ?? null;
        unset($payload['document']);

        if (! $document instanceof UploadedFile) {
            return ['payload' => $payload, 'path' => null];
        }

        $path = $document->store('rentals/collaterals/'.now()->format('Y/m'), 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'document' => 'Dokumen jaminan gagal disimpan.',
            ]);
        }

        $payload['document_path'] = $path;
        $payload['document_original_name'] = $document->getClientOriginalName();
        $payload['document_mime_type'] = $document->getMimeType();
        $payload['document_size'] = $document->getSize();

        return ['payload' => $payload, 'path' => $path];
    }

    /** @param list<string> $paths */
    public function cleanup(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }
}
