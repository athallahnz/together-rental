<?php

namespace Tests\Unit\Stage4;

use Tests\TestCase;

class Stage4BilingualMessagesTest extends TestCase
{
    public function test_operational_flash_messages_and_pdf_labels_match_both_locales(): void
    {
        $id = require lang_path('id/uat035b_stage4.php');
        $en = require lang_path('en/uat035b_stage4.php');

        foreach (['flash' => 25, 'pdf' => 70] as $section => $minimum) {
            $this->assertArrayHasKey($section, $id);
            $this->assertArrayHasKey($section, $en);
            $this->assertSame(array_keys($id[$section]), array_keys($en[$section]));
            $this->assertGreaterThanOrEqual($minimum, count($id[$section]));

            foreach ($id[$section] as $key => $value) {
                $this->assertNotSame('', trim($value), $key);
                $this->assertNotSame('', trim($en[$section][$key]), $key);
                preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $value, $idVars);
                preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $en[$section][$key], $enVars);
                sort($idVars[1]);
                sort($enVars[1]);
                $this->assertSame($idVars[1], $enVars[1], $key);
            }
        }
    }

    public function test_booking_rental_transfer_toasts_switch_with_the_user_locale(): void
    {
        app()->setLocale('id');
        $this->assertSame('Booking BKG-TEST berhasil dibuat.', __('uat035b_stage4.flash.booking_created', ['reference' => 'BKG-TEST']));
        $this->assertSame('Pengembalian RET-TEST berhasil diproses.', __('uat035b_stage4.flash.return_recorded', ['reference' => 'RET-TEST']));
        $this->assertSame('Transfer berhasil diperbarui.', __('uat035b_stage4.flash.transfer_updated'));
        $this->assertSame('SURAT PERJANJIAN SEWA', __('uat035b_stage4.pdf.SURAT PERJANJIAN SEWA'));

        app()->setLocale('en');
        $this->assertSame('Booking BKG-TEST created successfully.', __('uat035b_stage4.flash.booking_created', ['reference' => 'BKG-TEST']));
        $this->assertSame('Return RET-TEST processed successfully.', __('uat035b_stage4.flash.return_recorded', ['reference' => 'RET-TEST']));
        $this->assertSame('Transfer updated successfully.', __('uat035b_stage4.flash.transfer_updated'));
        $this->assertSame('RENTAL AGREEMENT', __('uat035b_stage4.pdf.SURAT PERJANJIAN SEWA'));
    }
}
