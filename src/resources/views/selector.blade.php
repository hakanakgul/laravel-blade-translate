{{-- 
    Language Selector Blade Component
    Kullanım: @include('components.language-selector')
    Kullanım 2: <x-localization::selector />
--}}
@php
    $currentLocale ??= app()->getLocale();
    $configLanguages = config('languages');
    // 3. Config verisini View'in beklediği $locales formatına dönüştür
$locales = collect($configLanguages)
    ->map(function ($details, $code) {
        return [
            'code' => $code,
            'country' => $details['country'],
            'language' => $details['language'],
            // Bayrak yolu örneği: public/assets/flags/ klasöründe olduğunu varsayarsak:
            'flagUrl' => $details['flag_url'],
        ];
    })
    ->values(); // Keys (tr, en) yapısını kaldırıp index array (0, 1) yapar

$selectorId = 'lang-sel-' . uniqid();
// 5. Aktif dili bul (Config'de olmayan bir dil seçiliyse varsayılan olarak ilki al)
    $activeLocale = $locales->firstWhere('code', $currentLocale) ?? $locales->first();
@endphp

<div id="{{ $selectorId }}"></div>

<script>
    (function() {
        const HOST_ID = @json($selectorId);
        const LOCALES = @json($locales);
        const CURRENT = @json($currentLocale);
        const LABEL = @json(__('Ülkenizi seçiniz'));

        /* ── Shadow Root ──────────────────────────────────────────── */
        const host = document.getElementById(HOST_ID);
        const shadow = host.attachShadow({
            mode: 'open'
        });

        /* ── CSS (tamamen izole) ──────────────────────────────────── */
        const style = document.createElement('style');
        style.textContent = `
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* Toggle button */
        .ls-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1.5px solid #374151;
            border-radius: 12px;
            padding: 6px 16px;
            font-size: 14px;
            font-family: inherit;
            color: #374151;
            background: transparent;
            cursor: pointer;
            transition: background .2s, color .2s;
        }
        .ls-btn:hover { background: #374151; color: #fff; }

        .ls-btn img { 
            width: 24px; 
            height: 18px; 
            border-radius: 3px; 
            object-fit: contain; 
            border: 1px solid rgba(0,0,0,0.1); /* Beyaz bayraklar için sınır */
        }

        /* Overlay */
        .ls-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.3);
            z-index: 9998;
        }

        /* Modal */
        .ls-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 16px;
            z-index: 9999;
        }
        .ls-card {
            width: 100%;
            max-width: 448px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,.15);
            color: #374151;
            font-family: system-ui, sans-serif;
        }

        /* Header */
        .ls-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px;
        }
        .ls-header h2 { font-size: 17px; font-weight: 600; }
        .ls-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background .15s;
        }
        .ls-icon-btn:hover { background: #f3f4f6; }

        /* Search */
        .ls-search { border-bottom: 1px solid #e5e7eb; padding: 16px; }
        .ls-search input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 14px;
            font-family: inherit;
            color: #111;
            outline: none;
            transition: box-shadow .15s;
        }
        .ls-search input:focus { box-shadow: 0 0 0 3px rgba(55,65,81,.25); border-color: #374151; }

        /* List */
        .ls-list { max-height: 260px; overflow-y: auto; list-style: none; }
        .ls-list li button {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 10px 16px;
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            text-align: left;
            transition: background .1s;
        }
        .ls-list li button:hover { background: #f9fafb; }
        .ls-list li[aria-selected="true"] button { background: #f3f4f6; }
        .ls-list img { width: 28px; height: 21px; border-radius: 3px; object-fit: contain; margin-right: 12px; flex-shrink: 0; border: 1px solid rgba(0,0,0,0.1);}        
        .ls-list .ls-country { font-size: 14px; font-weight: 500; color: #111; }
        .ls-list .ls-lang   { font-size: 12px; color: #6b7280; margin-top: 1px; }
        .ls-empty { padding: 10px 16px; color: #6b7280; font-size: 14px; }
    `;

        /* ── State ────────────────────────────────────────────────── */
        let isOpen = false;
        let search = '';
        let current = CURRENT;
        let cursorPosition = null; // <--- BU SATIRI EKLE
        function getActive() {
            return LOCALES.find(l => l.code === current) || LOCALES[0] || null;
        }

        // YENİ HELPER: Aksanları kaldıran fonksiyon (ö -> o, ş -> s yapar)
        function normalize(str) {
            return str.toLocaleLowerCase('tr') // Türkçe uyumlu küçültme
                .normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // Aksanları sil
        }

        function filtered() {
            const q = normalize(search); // Aranan kelimeyi normalize et

            return LOCALES.filter(l =>
                // Ülke veya Dil adını normalize edip aratıyoruz
                normalize(l.country).includes(q) ||
                normalize(l.language).includes(q)
            );
        }

        /* ── Icons (inline SVG – lucide) ─────────────────────────── */
        const iconChevronLeft =
            `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>`;
        const iconX =
            `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;

        /* ── Render ───────────────────────────────────────────────── */
        function render() {
            const active = getActive();
            const items = filtered();

            shadow.innerHTML = '';
            shadow.appendChild(style.cloneNode(true));

            /* Toggle button */
            const btn = document.createElement('button');
            btn.className = 'ls-btn';
            if (active) {
                btn.innerHTML = `<img src="${active.flagUrl}" alt="${active.code}"><span>${active.language}</span>`;
            } else {
                btn.textContent = 'Select Language';
            }
            btn.addEventListener('click', () => {
                isOpen = true;
                render();
            });
            shadow.appendChild(btn);

            if (!isOpen) return;

            /* Overlay */
            const overlay = document.createElement('div');
            overlay.className = 'ls-overlay';
            overlay.addEventListener('click', () => {
                isOpen = false;
                search = '';
                render();
            });
            shadow.appendChild(overlay);

            /* Modal */
            const modal = document.createElement('div');
            modal.className = 'ls-modal';

            const card = document.createElement('div');
            card.className = 'ls-card';
            card.addEventListener('click', e => e.stopPropagation());

            /* Header */
            card.innerHTML = `
            <div class="ls-header">
                <button class="ls-icon-btn ls-close">${iconChevronLeft}</button>
                <h2>${LABEL}</h2>
                <button class="ls-icon-btn ls-close">${iconX}</button>
            </div>
            <div class="ls-search">
                <input type="text" placeholder="Search for a country" value="${search.replace(/"/g, '&quot;')}">
            </div>
        `;

            /* Close buttons */
            card.querySelectorAll('.ls-close').forEach(b =>
                b.addEventListener('click', () => {
                    isOpen = false;
                    search = '';
                    render();
                })
            );

            /* Search input */
            const input = card.querySelector('input');
            input.addEventListener('input', e => {
                search = e.target.value;
                cursorPosition = e.target.selectionStart; // <--- İMLEÇ KONUMUNU KAYDET
                render();
            });
            /* Sonraki frame'de focus ver (render sonrası) */
            /* Sonraki frame'de focus ver ve İMLECİ ESKİ YERİNE KOY */
            setTimeout(() => {
                if (input) {
                    input.focus();
                    if (cursorPosition !== null) {
                        input.setSelectionRange(cursorPosition, cursorPosition);
                    }
                }
            }, 0);

            /* List */
            const ul = document.createElement('ul');
            ul.className = 'ls-list';
            ul.setAttribute('role', 'listbox');

            if (items.length === 0) {
                ul.innerHTML = `<li class="ls-empty">No results</li>`;
            } else {
                items.forEach(loc => {
                    const li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.setAttribute('aria-selected', loc.code === current ? 'true' : 'false');

                    const locBtn = document.createElement('button');
                    locBtn.innerHTML = `
                    <img src="${loc.flagUrl}" alt="${loc.code}">
                    <div>
                        <div class="ls-country">${loc.country}</div>
                        <div class="ls-lang">${loc.language}</div>
                    </div>
                `;
                    locBtn.addEventListener('click', () => {
                        current = loc.code;
                        isOpen = false;
                        search = '';
                        window.location.href = `/lang/${loc.code}`;
                        /* Laravel locale değişikliği için isteğe bağlı redirect/form */
                        const event = new CustomEvent('locale-change', {
                            bubbles: true,
                            composed: true, // Shadow DOM'dan dışarı çıkabilsin
                            detail: {
                                code: loc.code
                            }
                        });
                        host.dispatchEvent(event);
                    });

                    li.appendChild(locBtn);
                    ul.appendChild(li);
                });
            }

            card.appendChild(ul);
            modal.appendChild(card);
            shadow.appendChild(modal);
        }

        render();
    })();
</script>
