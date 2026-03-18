<?php

namespace HakanAkgul\LaravelLocalizationBlade\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use HakanAkgul\LaravelLocalizationBlade\Services\Translate;

class TranslateToLanguageCommand extends Command
{
    protected $signature = 'translate:all
        {--lang=         : Belirli bir dile çevir (varsayılan: tüm aktif diller)}
        {--engine=google : Çeviri motoru (google|openai)}
        {--source=en     : Kaynak dil JSON dosyası (varsayılan: en)}
        {--module=       : Modül adı (Laravel Modules desteği)}';

    protected $description = 'en.json içeriğini tüm aktif dillere veya belirli bir dile çevirir.';

    public function handle(): int
    {
        $engine     = $this->option('engine') ?: 'google';
        $sourceLang = $this->option('source')  ?: 'en';
        $langOption = $this->option('lang');
        $moduleName = $this->option('module');

        // Kaynak ve ana dil (tr) bu komut tarafından yönetilmez.
        // tr.json → translate:all tarafından oluşturulur (identity map)
        // en.json → translate:all tarafından oluşturulur (tr→en çevirisi)
        $fallbackLocale = config('app.fallback_locale', 'tr');
        $skipLocales    = array_unique([$sourceLang, $fallbackLocale]);

        $configLanguages = Config::get('languages');

        if (!$configLanguages || !is_array($configLanguages)) {
            $this->error('HATA: config/languages.php bulunamadı veya geçersiz.');
            return self::FAILURE;
        }

        $allLocales = array_keys($configLanguages);

        // Hedef dilleri belirle: tek dil veya tümü (kaynak + ana dil hariç)
        $targetLanguages = $langOption
            ? [$langOption]
            : array_values(array_filter($allLocales, fn($l) => !in_array($l, $skipLocales)));

        if (empty($targetLanguages)) {
            $this->warn('Çevrilecek hedef dil bulunamadı.');
            return self::SUCCESS;
        }

        $langPath   = $moduleName
            ? base_path("Modules/{$moduleName}/Resources/lang")
            : base_path('lang');

        $sourceFile = "{$langPath}/{$sourceLang}.json";

        if (!File::exists($sourceFile)) {
            $this->error(
                "{$sourceFile} bulunamadı. Lütfen önce " .
                "\"php artisan translate:all" . ($moduleName ? " --module={$moduleName}" : '') . "\" " .
                "komutunu çalıştırın."
            );
            return self::FAILURE;
        }

        $sourceTranslations = json_decode(File::get($sourceFile), true) ?? [];

        if (empty($sourceTranslations)) {
            $this->warn("{$sourceFile} boş, işlem yapılmadı.");
            return self::SUCCESS;
        }

        if (!File::exists($langPath)) {
            File::makeDirectory($langPath, 0755, true);
        }

        foreach ($targetLanguages as $target) {
            $this->line('');
            $this->line(
                "Dil: <fg=cyan>{$target}</> | Motor: <fg=yellow>{$engine}</>" .
                ($moduleName ? " | Modül: <fg=magenta>{$moduleName}</>" : '')
            );

            $targetFile           = "{$langPath}/{$target}.json";
            $existingTranslations = File::exists($targetFile)
                ? (json_decode(File::get($targetFile), true) ?? [])
                : [];

            $addedCount   = 0;
            $skippedCount = 0;

            foreach ($sourceTranslations as $key => $sourceValue) {
                // Mevcut çeviriyi atla
                if (array_key_exists($key, $existingTranslations)) {
                    $skippedCount++;
                    continue;
                }

                $result     = new Translate($target, $sourceValue, 'x-translate-not-available', $engine, $sourceLang);
                $translated = $result->translated;

                if (!$translated || str_starts_with($translated, '[ÇEVİRİ BAŞARISIZ')) {
                    $this->warn("  ✗ Başarısız, atlandı: \"{$key}\"");
                    continue;
                }

                $existingTranslations[$key] = $translated;
                $addedCount++;

                File::put($targetFile, json_encode($existingTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $this->line("  <fg=green>✓</> {$key} <fg=gray>→</> {$translated}");
            }

            $this->info(
                "  ✅ {$target}.json güncellendi. " .
                "<fg=green>{$addedCount} yeni</> | <fg=gray>{$skippedCount} mevcut</>"
            );
        }

        return self::SUCCESS;
    }
}
