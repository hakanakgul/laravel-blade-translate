<?php

namespace HakanAkgul\LaravelLocalizationBlade\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use HakanAkgul\LaravelLocalizationBlade\Services\Translate;

class ChangeTranslateKeywordCommand extends Command
{
    protected $signature = 'translate
        {--all              : Tamamlandığında translate:all komutunu otomatik çalıştır}
        {--module=          : Modül adı (Laravel Modules desteği)}
        {--engine=google    : Çeviri motoru (google|openai)}
        {--dry-run          : Dosya değiştirmeden nelerin ekleneceğini göster}';

    protected $description = 'Blade ve PHP dosyalarındaki __("...") ifadelerini tarar, tr.json ve en.json dosyalarını günceller.';

    /**
     * __('...') veya __("...") ifadelerini yakalar.
     * Kaçış karakterlerini ve karışık tırnak kullanımını doğru işler.
     *
     * Grup 1: Açılış tırnak karakteri (' veya ")
     * Grup 2: String içeriği (escaped karakterler dahil)
     * \1 backreference: Açılış tırnak ile aynı tırnak ile kapanmak zorunda
     */
    private const SCAN_PATTERN = '/__\(\s*([\'"])((?:[^\\\\]|\\\\.)*?)\1/';

    public function handle(): int
    {
        $moduleName = $this->option('module');
        $engine     = $this->option('engine') ?: 'google';
        $dryRun     = $this->option('dry-run');

        [$scanPaths, $langPath] = $this->resolvePaths($moduleName);

        // Dosyaları tara, bulunan string'leri ve tarama istatistiklerini al
        [$strings, $scanStats] = $this->scanFiles($scanPaths);

        $this->printScanSummary($scanStats, count($strings));

        if (empty($strings)) {
            $this->warn('Hiç __("...") ifadesi bulunamadı.');
            return self::SUCCESS;
        }

        // Mevcut çeviri dosyalarını yükle
        $trJsonPath     = $langPath . '/tr.json';
        $enJsonPath     = $langPath . '/en.json';
        $trTranslations = $this->loadJson($trJsonPath);
        $enTranslations = $this->loadJson($enJsonPath);

        // tr.json'da henüz bulunmayan yeni string'leri belirle
        $newStrings    = array_values(array_filter($strings, fn($s) => !array_key_exists($s, $trTranslations)));
        $existingCount = count($strings) - count($newStrings);

        $this->line("Mevcut: <fg=green>{$existingCount}</> | Yeni: <fg=yellow>" . count($newStrings) . "</>");
        $this->line('');

        if (empty($newStrings)) {
            $this->info('Tüm string\'ler güncel. İşlem yapılmadı.');
            $this->runTranslateLangIfRequested($moduleName, $engine);
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('--dry-run modu: Dosyalar değiştirilmeyecek.');
            $this->line('');
            foreach ($newStrings as $str) {
                $this->line("  <fg=yellow>+</> {$str}");
            }
            return self::SUCCESS;
        }

        // Yeni string'leri Türkçe'den İngilizce'ye çevir
        $addedCount  = 0;
        $failedCount = 0;

        $this->line('Çevriliyor...');

        foreach ($newStrings as $turkishText) {
            $result      = new Translate('en', $turkishText, 'x-translate-not-available', $engine, 'tr');
            $englishText = $result->translated;

            if (!$englishText) {
                $failedCount++;
                $this->warn("  ✗ Çeviri başarısız, atlandı: \"{$turkishText}\"");
                continue;
            }

            // tr.json: anahtar = değer = Türkçe (identity map)
            // en.json: anahtar = Türkçe, değer = İngilizce
            $trTranslations[$turkishText] = $turkishText;
            $enTranslations[$turkishText] = $englishText;
            $addedCount++;

            $this->line("  <fg=green>✓</> {$turkishText} <fg=gray>→</> {$englishText}");
        }

        // lang/ dizini yoksa oluştur
        if (!File::exists($langPath)) {
            File::makeDirectory($langPath, 0755, true);
        }

        // JSON dosyalarını diske yaz
        File::put($trJsonPath, json_encode($trTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($enJsonPath, json_encode($enTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->info(
            "✅ {$addedCount} string tr.json ve en.json'a eklendi." .
            ($failedCount > 0 ? " <fg=yellow>({$failedCount} çeviri başarısız)</>" : '')
        );

        $this->runTranslateLangIfRequested($moduleName, $engine);

        return self::SUCCESS;
    }

    /**
     * Modül veya core moduna göre tarama ve lang dizin yollarını belirler.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function resolvePaths(?string $moduleName): array
    {
        if ($moduleName) {
            $this->line("Modül modu: <fg=cyan>{$moduleName}</>");
            $scanPaths = [
                base_path("Modules/{$moduleName}/Resources/views") => 'blade',
                base_path("Modules/{$moduleName}")                  => 'php',
            ];
            $langPath = base_path("Modules/{$moduleName}/Resources/lang");
        } else {
            $this->line('Core modu aktif.');
            $scanPaths = [
                resource_path('views') => 'blade',
                app_path()             => 'php',
            ];
            $langPath = base_path('lang');
        }

        return [$scanPaths, $langPath];
    }

    /**
     * Verilen dizinlerdeki dosyaları tarar ve bulunan benzersiz string'leri döner.
     *
     * @param  array<string, string>  $scanPaths  ['dizin_yolu' => 'blade'|'php']
     * @return array{0: string[], 1: array<int, array{path: string, type: string, count: int, found: bool}>}
     */
    private function scanFiles(array $scanPaths): array
    {
        $strings = [];
        $stats   = [];

        foreach ($scanPaths as $basePath => $type) {
            if (!File::exists($basePath)) {
                $stats[] = ['path' => $basePath, 'type' => $type, 'count' => 0, 'found' => false];
                continue;
            }

            $files     = collect(File::allFiles($basePath))->filter(
                fn(\SplFileInfo $f) => $this->matchesType($f->getFilename(), $type)
            );
            $fileCount = $files->count();
            $stats[]   = ['path' => $basePath, 'type' => $type, 'count' => $fileCount, 'found' => true];

            foreach ($files as $file) {
                $content = File::get($file->getRealPath());

                if (preg_match_all(self::SCAN_PATTERN, $content, $matches)) {
                    foreach ($matches[2] as $raw) {
                        // Backslash kaçış ifadelerini çöz ve boşlukları temizle
                        $text = trim(stripslashes($raw));
                        if ($text !== '') {
                            $strings[$text] = true;
                        }
                    }
                }
            }
        }

        return [array_keys($strings), $stats];
    }

    /**
     * Dosya adının belirtilen türle eşleşip eşleşmediğini kontrol eder.
     * 'php' türü .blade.php dosyalarını hariç tutar — bunlar 'blade' tarafından taranır.
     */
    private function matchesType(string $filename, string $type): bool
    {
        return match ($type) {
            'blade' => str_ends_with($filename, '.blade.php'),
            'php'   => str_ends_with($filename, '.php') && !str_ends_with($filename, '.blade.php'),
            default => false,
        };
    }

    /**
     * Tarama sonucunu kullanıcıya gösterir.
     */
    private function printScanSummary(array $stats, int $totalStrings): void
    {
        $this->line('');
        foreach ($stats as $stat) {
            $status = $stat['found']
                ? "<fg=green>{$stat['count']} dosya</>"
                : '<fg=red>dizin bulunamadı</>';
            $this->line("  [{$stat['type']}] {$stat['path']}  →  {$status}");
        }
        $this->line('');
        $this->line("Toplam <fg=cyan>{$totalStrings}</> benzersiz string bulundu.");
    }

    /**
     * JSON dosyasını okur. Dosya yoksa boş array döner.
     */
    private function loadJson(string $path): array
    {
        if (!File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?? [];
    }

    /**
     * --all bayrağı varsa translate:all komutunu çalıştırır.
     */
    private function runTranslateLangIfRequested(?string $moduleName, string $engine): void
    {
        if (!$this->option('all')) {
            return;
        }

        $this->line('');
        $this->info('Dil çevirisi başlatılıyor...');

        $args = ['--engine' => $engine];

        if ($moduleName) {
            $args['--module'] = $moduleName;
        }

        $this->call('translate:all', $args);
    }
}
