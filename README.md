# Laravel Blade Translate

Laravel projelerinde çok dilli (i18n) yapıyı sıfır sürtünmeyle kuran, Blade ve PHP dosyalarını otomatik tarayan, Google Translate veya OpenAI ile çeviren ve hazır bir dil seçici bileşeni sunan kapsamlı bir Laravel paketidir.

Geliştirici yalnızca kendi ana dilinde yazar. Gerisi pakete aittir.

---

## İçindekiler

1. [Kurulum](#kurulum)
2. [Zorunlu Yapılandırma](#zorunlu-yapılandırma)
3. [Geliştirici Kullanım Kılavuzu](#geliştirici-kullanım-kılavuzu)
4. [Konsol Komutları](#konsol-komutları)
5. [Dil Seçici Bileşeni](#dil-seçici-bileşeni)
6. [Modüler Mimari Desteği](#modüler-mimari-desteği)
7. [Sık Karşılaşılan Sorunlar](#sık-karşılaşılan-sorunlar)
8. [Paket Geliştiricisi İçin](#paket-geliştiricisi-için)

---

## Kurulum

```bash
composer require hakanakgul/laravel-blade-translate
```

Güncelleme için:

```bash
composer update hakanakgul/laravel-blade-translate
```

Kurulumdan sonra önbelleği temizleyin:

```bash
php artisan optimize:clear
```

---

## Zorunlu Yapılandırma

### 1. `config/app.php` — Ana Dili Türkçe Olarak Ayarla

Sistem Türkçeyi kaynak dil olarak kullandığından bu değerlerin doğru olması kritiktir:

```php
// config/app.php
'locale'          => 'tr',
'fallback_locale' => 'tr',
```

> **Neden önemli?** Paket `fallback_locale` değerini okuyarak hangi dilin kaynak (ana) dil olduğuna karar verir. Bu değer yanlışsa `tr.json` oluşturulmaz ve middleware yanlış dile düşer.

### 2. `config/languages.php` — Desteklenecek Dilleri Belirle

Ayar dosyasını projeye çıkartın:

```bash
php artisan vendor:publish --tag=localization-config
```

Bu komut `config/languages.php` dosyasını projenize kopyalar. **Bundan sonra dil eklemek veya çıkarmak için yalnızca bu dosyayı düzenleyin** — paketteki orijinal dosyaya dokunmayın, değişiklikleriniz yok sayılır.

```php
// config/languages.php
return [
    'tr' => ['country' => 'Türkiye',       'language' => 'Türkçe',   'flag_url' => 'https://flagcdn.com/w40/tr.png'],
    'en' => ['country' => 'United States', 'language' => 'English',  'flag_url' => 'https://flagcdn.com/w40/us.png'],
    'de' => ['country' => 'Germany',       'language' => 'Deutsch',  'flag_url' => 'https://flagcdn.com/w40/de.png'],
    // diğer diller...
];
```

Değişiklikten sonra önbelleği temizleyin:

```bash
php artisan optimize:clear
```

> Bu dosya **yalnızca** hangi dillerin sistemde aktif olacağını ve dil seçicide nasıl gösterileceğini tanımlar. Başka bir şey için kullanılmaz.

### 3. OpenAI Entegrasyonu (İsteğe Bağlı)

Google Translate yerine OpenAI kullanmak için `.env` dosyasına ekleyin:

```env
OPENAI_API_KEY=sk-...
```

`config/services.php` içinde de tanımlanmış olmalı:

```php
'openai' => [
    'key' => env('OPENAI_API_KEY'),
],
```

---

## Geliştirici Kullanım Kılavuzu

### Temel Prensip

Geliştirici her yerde standart Laravel `__()` fonksiyonunu kullanır — Türkçe string ile:

```php
__('Hoş Geldiniz')
```

`translate` komutu çalıştırıldığında tüm `__('...')` ifadeleri otomatik bulunur, `tr.json` ve `en.json` güncellenir. Kaynak dosyalara **hiç dokunulmaz**.

---

### Blade Dosyalarında Kullanım

```blade
<h1>{{ __('Sisteme Hoş Geldiniz') }}</h1>
<button>{{ __('Kaydet') }}</button>
<p>{{ __('Merhaba, :name', ['name' => $user->name]) }}</p>

{{-- String içinde tırnak kullanımı sorunsuz çalışır --}}
<span>{{ __("Kullanıcı adı 'zorunlu' bir alandır") }}</span>
<span>{{ __('İşlem "başarıyla" tamamlandı') }}</span>
```

### PHP Dosyalarında Kullanım

Controller, Model, Service veya herhangi bir PHP dosyasında aynı şekilde:

```php
// Controller
return response()->json(['message' => __('İşlem başarılı')]);

// Exception
throw new \Exception(__('Kayıt bulunamadı'));

// Validation mesajları
$messages = [
    'email.required' => __('E-posta adresi zorunludur'),
    'email.email'    => __('Geçerli bir e-posta adresi giriniz'),
];

// Mail / Notification
$subject = __('Hesabınız oluşturuldu');
```

> `translate` komutu hem `resources/views/` altındaki `.blade.php` dosyalarını hem de `app/` altındaki tüm `.php` dosyalarını tarar.

---

### Tam İş Akışı

**1. Kodu yazın** — her yerde Türkçe `__('...')` kullanın.

**2. Tarama ve çeviri komutunu çalıştırın:**

```bash
# Sadece tr.json ve en.json güncelle
php artisan translate

# tr.json + en.json güncelle, ardından tüm dillere dağıt
php artisan translate --all

# OpenAI motoru ile
php artisan translate --all --engine=openai

# Önce ne yapacağını gör, dosyalara dokunma
php artisan translate --dry-run
```

**3. Dil seçiciyi layout'a ekleyin:**

```blade
<x-localization::selector />
```

**Bu kadar.** Uygulama artık `config/languages.php`'de tanımlı tüm dilleri destekler.

---

### Yeni Geliştirme Döngüsü

Projeye her yeni özellik eklendiğinde tek yapılması gereken:

1. Yeni Türkçe string'leri her zamanki gibi `__('...')` ile yaz
2. Komut çalıştır: `php artisan translate --all`
3. Mevcut çeviriler korunur, sadece yeni olanlar eklenir

---

## Konsol Komutları

### `translate`

Blade ve PHP dosyalarını tarar. `tr.json` ve `en.json` dosyalarını oluşturur veya günceller. **Kaynak dosyalara dokunmaz.**

```bash
php artisan translate [seçenekler]
```

| Seçenek | Açıklama |
|---------|----------|
| `--all` | Bittikten sonra `translate:all` komutunu otomatik tetikler |
| `--engine=google` | Çeviri motoru: `google` (varsayılan) veya `openai` |
| `--dry-run` | Nelerin ekleneceğini gösterir, hiçbir şey yazmaz |
| `--module=AdModul` | Modül modunda çalışır (Laravel Modules) |

**Nasıl çalışır:**
1. `resources/views/` → tüm `.blade.php` dosyaları taranır
2. `app/` → tüm `.php` dosyaları taranır (`.blade.php` hariç)
3. `__('...')` ifadeleri yakalanır, kaçış karakterleri çözülür
4. `tr.json`'da olmayan yeni string'ler Türkçe'den İngilizce'ye çevrilir
5. `lang/tr.json` güncellenir (key = value = Türkçe)
6. `lang/en.json` güncellenir (key = Türkçe, value = İngilizce)

---

### `translate:all`

`en.json` içeriğini `config/languages.php`'de tanımlı tüm dillere çevirir. `tr` ve `en` otomatik atlanır.

```bash
php artisan translate:all [seçenekler]
```

| Seçenek | Açıklama |
|---------|----------|
| `--lang=de` | Sadece belirli bir dile çevir |
| `--engine=google` | Çeviri motoru: `google` (varsayılan) veya `openai` |
| `--source=en` | Kaynak JSON dosyası (varsayılan: `en`) |
| `--module=AdModul` | Modül modunda çalışır |

**Davranış:** Hedef dil dosyasında zaten mevcut olan anahtarları atlar. Sadece eksik olanları çevirir. Bu sayede mevcut çevirilerin üzerine yazılmaz.

---

### `translate:prepare`

Eski `{_t(value="...")}` etiket formatını kullanan mevcut projeleri sisteme dahil etmek için kullanılan **migration aracıdır.** Bu etiketleri standart `{{ __('...') }}` formatına dönüştürür.

```bash
php artisan translate:prepare [--module=AdModul]
```

> Yeni projelerde bu komuta gerek yoktur. Yalnızca eski format kullanan dosyaları güncellemek için çalıştırılır.

**Desteklenen eski formatlar:**
- `{_t(value="Metin")}` → `{{ __('Metin') }}`
- `{{ _t($value="Metin") }}` → `{{ __('Metin') }}`

Dönüşüm tamamlandıktan sonra `translate` çalıştırılmalıdır.

---

## Dil Seçici Bileşeni

Hazır dil değiştirme menüsünü herhangi bir Blade dosyasına ekleyin:

```blade
<x-localization::selector />
```

**Özellikler:**
- Sıfır JavaScript/CSS bağımlılığı — tamamen vanilla
- Shadow DOM ile stil izolasyonu (uygulama stilleriyle çakışmaz)
- Arama desteği (Türkçe karakter duyarsız: `o` yazınca `ö` de bulunur)
- Her dil için bayrak, ülke adı ve dil adı gösterimi
- Dil değiştirildiğinde `locale-change` custom event'i tetiklenir

**Tasarımı Özelleştirme:**

```bash
php artisan vendor:publish --tag=localization-views
```

Bileşen `resources/views/vendor/localization/components/selector.blade.php` konumuna kopyalanır. Orijinal dosya güncellense bile artık projedeki kopya kullanılır.

---

## Modüler Mimari Desteği

nWidart/laravel-modules gibi HMVC yapısı kullanan projelerde her modülün çevirileri izole tutulabilir:

```bash
# Modülü tara ve çevir
php artisan translate --all --module=Kutuphane

# Sadece belirli dile çevir
php artisan translate:all --lang=de --module=Kutuphane
```

**Modül modu dizin yapısı:**

```
Modules/Kutuphane/
├── Resources/
│   ├── views/          ← blade taraması buradan
│   └── lang/
│       ├── tr.json     ← oluşturulur
│       ├── en.json     ← oluşturulur
│       └── de.json     ← translate:all ile oluşturulur
└── Http/               ← php taraması buradan (tüm alt dizinler)
```

Modül çevirileri ana projenin `lang/` klasörüne kesinlikle dokunmaz.

---

## Sık Karşılaşılan Sorunlar

**Çeviriler görünmüyor veya hata var:**
```bash
php artisan optimize:clear
php artisan view:clear
```

**`tr.json` oluşturulmuyor:**
`config/app.php` dosyasında `fallback_locale` değerinin `'tr'` olduğundan emin olun.

**`en.json` bulunamadı hatası (`translate:all`):**
`translate:all` komutunu çalıştırmadan önce `translate` çalıştırılmış olmalıdır. `en.json` bu komutla oluşturulur.

**PHP dosyalarındaki çeviriler taranmıyor:**
Tarama `app/` dizinini kapsar. Bu dizin dışında bir yerde PHP dosyanız varsa (örn. `domain/`) `translate` onu görmez — dosyayı `app/` altına taşıyın veya geliştirici rehberinin ilgili bölümüne bakın.

**Dil seçicideki diller fazla veya eksik:**
`config/languages.php` dosyasını kontrol edin. Değişiklikten sonra `php artisan optimize:clear` çalıştırın.

---

## Paket Geliştiricisi İçin

Bu bölüm paketin kaynak kodunu geliştiren yazılımcılara yöneliktir.

### Mimari Genel Bakış

```
src/
├── Console/Commands/
│   ├── ChangeTranslateKeywordCommand.php   → translate
│   ├── TranslateToLanguageCommand.php      → translate:all
│   └── TranslatePrepareCommand.php         → translate:prepare
├── Http/Middleware/
│   └── LocalizationMiddleware.php          → Her request'te locale ayarlar
├── Services/
│   └── Translate.php                       → Google Translate & OpenAI adaptörü
├── resources/views/
│   └── selector.blade.php                  → Dil seçici bileşeni
├── routes/
│   └── web.php                             → /lang/{locale} rotası
├── config/
│   └── languages.php                       → Varsayılan dil tanımları
└── LocalizationServiceProvider.php         → Paket bootstrap
```

### Temel Tasarım Kararları

**Neden `{_t(...)}` değil de `__('...')` taranıyor?**
Önceki tasarımda özel etiketler blade dosyalarına yazılıyor, komut çalışınca standart formata dönüştürülüyordu. Bu yaklaşım dosyaları değiştirdiği için geri alınamaz ve PHP dosyalarında kullanılamaz. Mevcut tasarımda hiçbir kaynak dosya değiştirilmez — komut yalnızca `lang/` dizinini yönetir.

**Neden pivot dil olarak İngilizce kullanılıyor?**
`tr → en → diğer diller` zinciri tercih edildi. Bunun sebebi Google Translate ve OpenAI'nin en yüksek kaliteli çeviriyi İngilizce üzerinden vermesidir. Türkçe'den doğrudan Japonca'ya gitmek yerine Türkçe → İngilizce → Japonca yolu daha doğal sonuçlar üretir.

**`tr.json` neden identity map (key = value)?**
Laravel `__('Merhaba')` çağrısında aktif locale'in JSON dosyasını arar. Locale `tr` iken `tr.json`'da `"Merhaba": "Merhaba"` yoksa Laravel string'i olduğu gibi döner — bu aslında çalışır. Ancak `tr.json`'u açıkça oluşturmak, eksik çeviri tespitini (`translate:status` gibi bir komut için) mümkün kılar ve sistemin tutarlı davranmasını sağlar.

### Regex Deseni

Tüm tarama komutlarında kullanılan merkezi desen:

```
/__\(\s*(['"])((?:[^\\]|\\.)*?)\1/
```

| Parça | Anlamı |
|-------|--------|
| `__\(` | Literal `__(` |
| `\s*` | İsteğe bağlı boşluk |
| `(['"])` | Açılış tırnak — grup 1 |
| `((?:[^\\]\|\\.)*)` | String içeriği — grup 2 |
| `[^\\]` | Backslash olmayan herhangi bir karakter |
| `\\.` | Backslash + herhangi bir karakter (kaçış ifadesi) |
| `\1` | Backreference: açılış tırnak ile aynı tırnak ile kapat |

Yakalanan ham metin `stripslashes()` ile işlenir. Bu sayede `__('it\'s fine')` → JSON key `it's fine` olur.

### Çeviri Akışı

```
translate çalışır
    │
    ├─ resources/views/**/*.blade.php  ──┐
    │                                    ├─→ __('...') regex ile taranır
    ├─ app/**/*.php (blade hariç)      ──┘
    │
    ├─ tr.json yüklenir (mevcut)
    ├─ Fark alınır → yeni string'ler belirlenir
    │
    ├─ Her yeni string için:
    │       Türkçe → Google/OpenAI → İngilizce
    │
    ├─ tr.json güncellenir (identity map)
    └─ en.json güncellenir (key=Türkçe, value=İngilizce)

translate:all çalışır
    │
    ├─ en.json okunur
    ├─ tr ve en atlanır (kaynak + ana dil)
    └─ Her hedef dil için:
            İngilizce → Google/OpenAI → Hedef dil JSON
```

### Yeni Dil Motoru Eklemek

`Translate` servisi adaptör deseni ile genişletilebilir. Yeni bir motor eklemek için:

1. `Translate.php` içine `private function translateWithXxx(...)` metodu ekle
2. Constructor'daki `match` bloğuna yeni motoru ekle
3. Komutlardaki `--engine` açıklamasını güncelle

### Dikkat Edilmesi Gerekenler

- `languages.php` sadece dil tanımları içerir. Teknik yapılandırma (scan path, engine config vb.) buraya girmez.
- `translate:all` komutu `tr` ve `en`'i otomatik atlar. Bu dilleri manuel olarak `--lang` ile geçmek zararsızdır, komut onları işlemeden geçer.
- `fallback_locale` her zaman `config('app.fallback_locale', 'tr')` üzerinden okunur. Hardcoded `'tr'` kullanılmaz.
- `matchesType()` metodu `blade` ve `php` türlerini birbirinden ayırır. Blade dosyaları PHP taramasına dahil edilmez — her dosya yalnızca bir kez taranır.

---

## Lisans

MIT — dilediğiniz gibi kullanabilir, değiştirebilir ve dağıtabilirsiniz.
