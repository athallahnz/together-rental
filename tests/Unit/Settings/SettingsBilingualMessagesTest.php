<?php

namespace Tests\Unit\Settings;

use Tests\TestCase;

class SettingsBilingualMessagesTest extends TestCase
{
    public function test_company_feedback_follows_application_locale(): void
    {
        app()->setLocale('id');
        $this->assertSame(
            'Identitas dan regional perusahaan berhasil diperbarui.',
            __('uat035b_settings.company_updated'),
        );

        app()->setLocale('en');
        $this->assertSame(
            'Company identity and regional settings have been updated.',
            __('uat035b_settings.company_updated'),
        );
    }
}
