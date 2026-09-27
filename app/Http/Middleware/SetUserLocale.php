<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /**
     * An authenticated user always uses their saved preference. For guests,
     * accept only whitelisted cookie values set by the guest language route.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $preferred = $user instanceof User
            ? $user->locale
            : $request->cookie('guest_locale');
        App::setLocale(in_array($preferred, ['id', 'en'], true) ? $preferred : 'id');

        return $next($request);
    }
}
