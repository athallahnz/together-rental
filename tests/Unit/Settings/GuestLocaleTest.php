<?php

namespace Tests\Unit\Settings;

use App\Http\Middleware\SetUserLocale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GuestLocaleTest extends TestCase
{
    public function test_guest_can_use_a_valid_english_cookie(): void
    {
        $this->assertSame('en', $this->resolveGuest('en'));
    }

    public function test_guest_can_use_an_indonesian_cookie(): void
    {
        $this->assertSame('id', $this->resolveGuest('id'));
    }

    public function test_invalid_cookie_falls_back_to_indonesian(): void
    {
        $this->assertSame('id', $this->resolveGuest('xx'));
    }

    public function test_saved_user_locale_overrides_guest_cookie(): void
    {
        $request = Request::create('/login', 'GET', [], ['guest_locale' => 'en']);
        $user = (new User)->forceFill(['locale' => 'id']);
        $request->setUserResolver(static fn () => $user);
        (new SetUserLocale)->handle($request, static fn (): Response => new Response);

        $this->assertSame('id', App::getLocale());
    }

    private function resolveGuest(string $cookie): string
    {
        $request = Request::create('/login', 'GET', [], ['guest_locale' => $cookie]);
        $request->setUserResolver(static fn (): null => null);
        (new SetUserLocale)->handle($request, static fn (): Response => new Response);

        return App::getLocale();
    }
}
