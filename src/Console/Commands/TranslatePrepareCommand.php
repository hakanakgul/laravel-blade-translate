<?php

namespace HakanAkgul\LaravelLocalizationBlade\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslatePrepareCommand extends Command
{
    protected $signature = 'translate:prepare {--module=}';
    protected $description = 'Eski {_t(value="...")} formatındaki ifadeleri standart {{ __("...") }} formatına dönüştürür. Mevcut projeleri sisteme dahil etmek için migration aracıdır.';

    /**
     * Dönüştürülecek eski format desenleri.
     *
     * Desen 1: {{ _t($value='...') }} veya {{ _t($value="...") }}
     *   — ChangeTranslateKeywordCommand'ın eski sürümünün ürettiği format
     *
     * Desen 2: {_t(value='...')} veya {_t(value="...")} (isteğe bağlı trans= parametresiyle)
     *   — Orijinal özel etiket formatı
     *
     * Her ikisi de standart {{ __('...') }} formatına dönüştürülür.
     */
    private const PATTERNS = [
        '/\{\{\s*_t\(\s*\$value\s*=\s*([\'"])((?:[^\\\\]|\\\\.)*?)\1[^)]*\)\s*\}\}/',
        '/\{_t\(\s*value\s*=\s*([\'"])((?:[^\\\\]|\\\\.)*?)\1[^)]*\)\}/',
    ];

    public function handle(): int
    {
        $moduleName = $this->option('module');
        $viewsPath  = $moduleName
            ? base_path("Modules/{$moduleName}/Resources/views")
            : resource_path('views');

        if (!File::exists($viewsPath)) {
            $this->error("Görünüm klasörü bulunamadı: {$viewsPath}");
            return self::FAILURE;
        }

        $this->line($moduleName
            ? "Modül modu: <fg=cyan>{$moduleName}</>"
            : 'Core modu aktif.'
        );
        $this->line("Taranan: {$viewsPath}");
        $this->line('');

        $files = collect(File::allFiles($viewsPath))
            ->filter(fn(\SplFileInfo $f) => str_ends_with($f->getFilename(), '.blade.php'));

        $totalReplaced = 0;
        $updatedFiles  = 0;

        foreach ($files as $file) {
            $content  = File::get($file->getRealPath());
            $original = $content;

            $fileReplacements = 0;

            foreach (self::PATTERNS as $pattern) {
                $content = preg_replace_callback(
                    $pattern,
                    function (array $match) use (&$fileReplacements): string {
                        $text    = stripslashes($match[2]);
                        // Metin içindeki tek tırnakları kaçış karakteriyle koru
                        $escaped = str_replace("'", "\\'", $text);
                        $fileReplacements++;
                        return "{{ __('{$escaped}') }}";
                    },
                    $content
                );
            }

            if ($content !== $original) {
                File::put($file->getRealPath(), $content);
                $updatedFiles++;
                $totalReplaced += $fileReplacements;
                $this->line("  <fg=green>✓</> {$file->getRelativePathname()} <fg=gray>({$fileReplacements} ifade)</>");
            }
        }

        $this->line('');

        if ($totalReplaced > 0) {
            $this->info("✅ {$updatedFiles} dosyada toplam {$totalReplaced} ifade standart formata dönüştürüldü.");
            $this->line('İpucu: Şimdi <fg=cyan>php artisan translate:all</> komutunu çalıştırabilirsiniz.');
        } else {
            $this->info('Dönüştürülecek eski format ifadesi bulunamadı.');
        }

        return self::SUCCESS;
    }
}
