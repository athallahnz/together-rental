<?php

namespace Tests\Unit\Stage3;

use Tests\TestCase;

class Stage3MessagesTest extends TestCase
{
    public function test_stage_three_flash_translations_have_matching_keys_and_placeholders(): void
    {
        $id = require lang_path('id/uat035b_stage3.php');
        $en = require lang_path('en/uat035b_stage3.php');
        $idMessages = $id['toast'];
        $enMessages = $en['toast'];

        $this->assertSame(array_keys($idMessages), array_keys($enMessages));
        $this->assertGreaterThanOrEqual(50, count($idMessages));

        foreach ($idMessages as $key => $value) {
            $this->assertNotSame('', trim($value), $key);
            $this->assertNotSame('', trim($enMessages[$key]), $key);
            preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $value, $idVars);
            preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $enMessages[$key], $enVars);
            sort($idVars[1]);
            sort($enVars[1]);
            $this->assertSame($idVars[1], $enVars[1], $key);
        }
    }

    public function test_toast_content_switches_with_the_application_locale(): void
    {
        app()->setLocale('id');
        $this->assertSame('Produk Contoh berhasil dibuat.', __('uat035b_stage3.toast.product_1', ['name' => 'Contoh']));

        app()->setLocale('en');
        $this->assertSame('Product Example created successfully.', __('uat035b_stage3.toast.product_1', ['name' => 'Example']));
    }
}
