<?php

namespace HakanAkgul\LaravelLocalizationBlade\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Translate
{
    public ?string $translated = null;
    public string $trans;

    private function translateWithGoogle(string $value, string $source, string $target): string
    {
        $translator = \Stichoza\GoogleTranslate\GoogleTranslate::class;
        $t = new $translator();
        $t->setSource($source);
        $t->setTarget($target);
        return $t->translate($value);
    }

    public function __construct(
        string $target = "en",
        string $value = "",
        string $trans = "x-translate-not-available",
        string $engine = "google",
        string $source = "tr" // varsayılan kaynak dili
    ) {
        if ($trans !== "x-translate-not-available") {
            $this->translated = $trans;
            return;
        }

        if ($engine === "openai") {
            $this->translated = $this->translateWithOpenAI($value, $source, $target);
        } else {
            $this->translated = $this->translateWithGoogle($value, $source, $target);
        }
    }

    private function translateWithOpenAI(string $value, string $sourceLang, string $targetLang): ?string
    {
        // Starter-kit ana projesinin config/services.php dosyasından API anahtarını çeker
        $apiKey = config('services.openai.key');

        $content = <<<PROMPT
You are a professional {$sourceLang} to {$targetLang} translator specializing in software interface and user experience. 
Translate the following sentence from {$sourceLang} to {$targetLang} using native grammar, appropriate tone, and natural fluency for end users.

Return only the translated sentence — without quotation marks, markdown, or explanations:

$value
PROMPT;

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4.1-nano-2025-04-14',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ],
            'temperature' => 0.3,
        ]);

        if ($response->failed()) {
            Log::error("OpenAI Çeviri Hatası", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null; // Çeviri başarısızsa null döner
        }

        $raw = $response->json('choices.0.message.content') ?? $value;
        return trim($this->stripQuotes($raw));
    }

    private function stripQuotes(string $text): string
    {
        return preg_replace('/^"(.*)"$/s', '$1', trim($text));
    }
}