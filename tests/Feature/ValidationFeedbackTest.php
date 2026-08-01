<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidationFeedbackTest extends TestCase
{
    public function test_default_application_locale_is_indonesian(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));
    }

    public function test_validation_messages_use_indonesian_labels(): void
    {
        $validator = Validator::make([], [
            'name' => ['required'],
            'email' => ['required', 'email'],
        ]);

        $this->assertSame('nama wajib diisi.', $validator->errors()->first('name'));
        $this->assertSame('email wajib diisi.', $validator->errors()->first('email'));
    }

    public function test_nested_item_validation_uses_business_friendly_label(): void
    {
        $validator = Validator::make([
            'items' => [
                ['quantity' => null],
            ],
        ], [
            'items.0.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $this->assertSame(
            'jumlah item wajib diisi.',
            $validator->errors()->first('items.0.quantity'),
        );
    }
}
