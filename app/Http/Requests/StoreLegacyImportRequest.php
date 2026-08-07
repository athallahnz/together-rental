<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class StoreLegacyImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('imports.upload');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'import_prefix' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'sql_file' => [
                'required',
                'file',
                'max:'.(int) config('legacy-import.max_upload_kilobytes', 102400),
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (
                        ! $value instanceof UploadedFile
                        || mb_strtolower($value->getClientOriginalExtension()) !== 'sql'
                    ) {
                        $fail('File sumber harus menggunakan ekstensi .sql.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $upload = $_FILES['sql_file'] ?? null;
        $errorCode = is_array($upload)
            ? (int) ($upload['error'] ?? UPLOAD_ERR_OK)
            : UPLOAD_ERR_OK;

        return [
            'branch_id.required' => 'Pilih kota/cabang tujuan import.',
            'branch_id.exists' => 'Kota/cabang tujuan import tidak tersedia.',
            'import_prefix.required' => 'PREFIX import wajib diisi.',
            'import_prefix.size' => 'PREFIX import wajib tepat 3 huruf.',
            'import_prefix.regex' => 'PREFIX import hanya boleh terdiri dari 3 huruf A-Z.',
            'sql_file.required' => 'Pilih file dump RentalV1 berformat .sql.',
            'sql_file.uploaded' => $this->uploadErrorMessage($errorCode),
            'sql_file.file' => 'Sumber yang dipilih tidak diterima sebagai file upload.',
            'sql_file.max' => 'Ukuran file melebihi batas aplikasi '.round(
                (int) config('legacy-import.max_upload_kilobytes', 102400) / 1024,
            ).' MB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'kota/cabang tujuan',
            'import_prefix' => 'PREFIX import',
            'sql_file' => 'file SQL RentalV1',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('import_prefix')) {
            $this->merge([
                'import_prefix' => strtoupper(trim((string) $this->input('import_prefix'))),
            ]);
        }

        $upload = $_FILES['sql_file'] ?? null;

        if (! is_array($upload)) {
            return;
        }

        $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_OK);

        if ($errorCode === UPLOAD_ERR_OK) {
            return;
        }

        $temporaryDirectory = (string) (ini_get('upload_tmp_dir') ?: sys_get_temp_dir());

        logger()->warning('Legacy import upload ditolak oleh runtime PHP.', [
            'php_upload_error' => $errorCode,
            'php_upload_error_message' => $this->uploadErrorMessage($errorCode),
            'client_filename' => $upload['name'] ?? null,
            'reported_size' => $upload['size'] ?? null,
            'content_length' => $this->server('CONTENT_LENGTH'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_tmp_dir' => $temporaryDirectory,
            'upload_tmp_dir_exists' => is_dir($temporaryDirectory),
            'upload_tmp_dir_writable' => is_writable($temporaryDirectory),
        ]);
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => sprintf(
                'File ditolak runtime PHP web karena melebihi upload_max_filesize=%s (post_max_size=%s). Restart server setelah mengubah php.ini.',
                ini_get('upload_max_filesize'),
                ini_get('post_max_size'),
            ),
            UPLOAD_ERR_FORM_SIZE => 'File melebihi batas ukuran yang ditetapkan oleh form.',
            UPLOAD_ERR_PARTIAL => 'Upload hanya diterima sebagian. Periksa koneksi lalu pilih dan unggah ulang file.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diterima oleh server.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP tidak menemukan folder temporary untuk menampung upload.',
            UPLOAD_ERR_CANT_WRITE => 'PHP gagal menulis upload ke folder temporary. Periksa izin tulis folder TEMP Windows.',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP.',
            default => sprintf(
                'File gagal diunggah pada runtime PHP (kode %d). upload_max_filesize=%s, post_max_size=%s.',
                $errorCode,
                ini_get('upload_max_filesize'),
                ini_get('post_max_size'),
            ),
        };
    }
}
