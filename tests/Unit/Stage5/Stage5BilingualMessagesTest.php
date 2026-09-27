<?php

namespace Tests\Unit\Stage5;

use App\Domain\Notifications\NotificationContentLocalizer;
use Tests\TestCase;

class Stage5BilingualMessagesTest extends TestCase
{
    public function test_finance_notification_import_and_reset_messages_have_matching_keys_and_placeholders(): void
    {
        $id = require lang_path('id/uat035b_stage5.php');
        $en = require lang_path('en/uat035b_stage5.php');

        $this->assertArrayHasKey('flash', $id);
        $this->assertArrayHasKey('flash', $en);
        $this->assertSame(array_keys($id['flash']), array_keys($en['flash']));
        $this->assertGreaterThanOrEqual(48, count($id['flash']));

        foreach ($id['flash'] as $key => $value) {
            $this->assertNotSame('', trim($value), $key);
            $this->assertNotSame('', trim($en['flash'][$key]), $key);
            preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $value, $idVars);
            preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $en['flash'][$key], $enVars);
            sort($idVars[1]);
            sort($enVars[1]);
            $this->assertSame($idVars[1], $enVars[1], $key);
        }
    }

    public function test_finance_and_import_flash_use_the_selected_locale(): void
    {
        $previous = app()->getLocale();

        try {
            app()->setLocale('id');
            $this->assertSame('Sesi kas berhasil dibuka.', __('uat035b_stage5.flash.cash_opened'));
            $this->assertSame('Pengeluaran EXP-001 berhasil dibayar.', __('uat035b_stage5.flash.expense_paid', ['reference' => 'EXP-001']));
            app()->setLocale('en');
            $this->assertSame('Cash session opened successfully.', __('uat035b_stage5.flash.cash_opened'));
            $this->assertSame('Expense EXP-001 paid successfully.', __('uat035b_stage5.flash.expense_paid', ['reference' => 'EXP-001']));
            $this->assertSame('Process queued and will run in the background.', __('uat035b_stage5.flash.import_queued'));
        } finally {
            app()->setLocale($previous);
        }
    }

    public function test_notification_template_localization_preserves_original_record_and_unknown_templates(): void
    {
        $localizer = new NotificationContentLocalizer;
        $title = 'Booking BKG-001 segera dimulai';
        $body = 'Booking BKG-001 untuk Pelanggan UAT di cabang PNG dijadwalkan mulai 26 Sep 2026, 10:00.';

        $this->assertSame(compact('title', 'body'), $localizer->localize('booking.starting_soon', $title, $body, 'id'));
        $this->assertSame([
            'title' => 'Booking BKG-001 starting soon',
            'body' => 'Booking BKG-001 for Pelanggan UAT at branch PNG is scheduled to start at 26 Sep 2026, 10:00.',
        ], $localizer->localize('booking.starting_soon', $title, $body, 'en'));
        $this->assertSame(compact('title', 'body'), $localizer->localize('custom.user_rule', $title, $body, 'en'));
        $this->assertSame(['title' => 'Customized title', 'body' => 'Customized content'], $localizer->localize('booking.starting_soon', 'Customized title', 'Customized content', 'en'));
    }
}
