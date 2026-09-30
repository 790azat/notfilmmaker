<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('app.locales')), 404);

        $request->session()->put('locale', $locale);

        return redirect()->to(url()->previous() === url()->current() ? '/' : url()->previous());
    }
}
