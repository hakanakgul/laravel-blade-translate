<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

Route::get('/lang/{locale}', function ($locale) {
    // Config dosyasını al (Artık detaylı bir dizi)
    $availableLocales = config('languages', []);

    // DİKKAT: Artık diller anahtar olduğu için 'array_key_exists' kullanıyoruz.
    // Eski kod: in_array($locale, $availableLocales) -> Yanlış, çünkü değerlere bakar.
    if (array_key_exists($locale, $availableLocales)) {
        Session::put('locale', $locale);
    }

    return redirect()->back();
})->name('lang.switch')->middleware('web');