<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class GuestLanguageRouteTest extends TestCase
{
    public function test_guest_can_save_a_supported_language(): void
    {
        $this->from('/login')
            ->post('/language/guest', ['locale' => 'en'])
            ->assertRedirect('/login')
            ->assertCookie('guest_locale');
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->from('/login')
            ->post('/language/guest', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }
}
