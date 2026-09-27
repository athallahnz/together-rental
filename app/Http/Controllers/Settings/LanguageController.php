<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/language');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(['id', 'en'])],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $user->forceFill([
            'locale' => $validated['locale'],
        ])->save();

        return to_route('language.edit')->with('toast', [
            'type' => 'success',
            'message' => $validated['locale'] === 'en'
                ? 'Language preference saved.'
                : 'Preferensi bahasa berhasil disimpan.',
        ]);
    }
}
