<?php

namespace HakanAkgul\LaravelLocalizationBlade\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class LocalizationMiddleware
{
    public function handle(Request $request, Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        // Config dosyasını al (Artık detaylı associative array)
        $availableLocales = Config::get('languages', []);

        // Session'da dil yoksa uygulamanın varsayılan dilini al
        $locale = session('locale', config('app.locale'));

        // DÜZELTME:
        // $availableLocales artık ['tr' => [...], 'en' => [...]] yapısında.
        // Bu yüzden 'tr' anahtarının varlığını kontrol etmeliyiz.
        if (array_key_exists($locale, $availableLocales)) {
            App::setLocale($locale);
        } else {
            // Eğer session'daki dil config'de yoksa varsayılan dile dön
            App::setLocale(config('app.fallback_locale', 'tr'));
        }

        return $next($request);
    }
}