<?php

namespace HakanAkgul\LaravelLocalizationBlade;

use Illuminate\Support\ServiceProvider;
use HakanAkgul\LaravelLocalizationBlade\Console\Commands\ChangeTranslateKeywordCommand;
use HakanAkgul\LaravelLocalizationBlade\Console\Commands\TranslateToLanguageCommand;
use HakanAkgul\LaravelLocalizationBlade\Console\Commands\TranslatePrepareCommand;
use Illuminate\Support\Facades\Blade;

class LocalizationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Kullanıcı vendor:publish ile kendi config dosyasını oluşturduysa ona dokunma.
        // mergeConfigFrom paket varsayılanlarını published config'in üzerine merge eder,
        // bu yüzden kullanıcının sildiği diller geri gelirdi. Bunun önüne geçiyoruz.
        if (!file_exists(config_path('languages.php'))) {
            $this->mergeConfigFrom(__DIR__ . '/config/languages.php', 'languages');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Eğer kullanıcı dilleri ana projeden yönetmek isterse, bu dosyayı dışarı aktarabilmesini sağlıyoruz
        // 1. Config dosyasını dışarı aktarılabilir yap
        $this->publishes([
            __DIR__ . '/config/languages.php' => config_path('languages.php'),
        ], 'localization-config');

        // 2. Görünümleri (Views) yükle ve namespace ver (Örn: localization::)
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'localization');

        // 3. İstenirse görünümlerin de dışarı aktarılabilmesini sağla
        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/vendor/localization'),
        ], 'localization-views');

        // 🔥 İŞTE ÇÖZÜM BURADA: Laravel'e paketimizin component klasörünü zorla öğretiyoruz
        Blade::anonymousComponentPath(__DIR__ . '/resources/views', 'localization');

        // Komutlar
        if ($this->app->runningInConsole()) {
            $this->commands([
                ChangeTranslateKeywordCommand::class,
                TranslateToLanguageCommand::class,
                TranslatePrepareCommand::class,
            ]);
        }

        // Rotalar
        if (file_exists(__DIR__ . '/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
            $this->app['router']->pushMiddlewareToGroup('web', \HakanAkgul\LaravelLocalizationBlade\Http\Middleware\LocalizationMiddleware::class);
        }
    }
}