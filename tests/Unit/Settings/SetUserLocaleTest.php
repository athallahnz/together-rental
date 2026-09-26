<?php

namespace Tests\Unit\Settings;

use App\Http\Middleware\SetUserLocale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SetUserLocaleTest extends TestCase
{
    public function test_an_authenticated_user_can_use_english(): void
    {
        $this->assertSame('en', $this->resolveLocale('en'));
    }

    public function test_an_authenticated_user_can_use_indonesian(): void
    {
        $this->assertSame('id', $this->resolveLocale('id'));
    }

    public function test_an_invalid_stored_locale_falls_back_to_indonesian(): void
    {
        $this->assertSame('id', $this->resolveLocale('xx'));
    }

    public function test_guest_requests_fall_back_to_indonesian(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(static fn (): null => null);
        (new SetUserLocale)->handle($request, static fn (): Response => new Response);

        $this->assertSame('id', App::getLocale());
    }

    private function resolveLocale(string $preferred): string
    {
        $user = (new User)->forceFill(['locale' => $preferred]);
        $request = Request::create('/settings/language');
        $request->setUserResolver(static fn () => $user);
        (new SetUserLocale)->handle($request, static fn (): Response => new Response);

        return App::getLocale();
    }
}
