# Printumo WooCommerce Integration v2.3.1

Profesjonalna wtyczka integrująca WooCommerce z Printumo API - z minimalistycznym, eleganckim konfiguratorem produktów canvas.

## 🎨 Nowy Minimalistyczny Design (v2.2)

### Paleta Kolorów:
- **Główny akcent**: `#773fc6` (fioletowy)
- **Kolor pomocniczy**: `#88d8d3` (turkusowy)
- **Tło**: `#FFFFFF` (białe)
- **Teksty**: `#333333` (ciemny szary)
- **Ramki**: `#E0E0E0` (jasny szary)
- **Hover**: `#5f2fa3` (ciemniejszy fiolet)

### Główne Usprawnienia v2.3:

#### 0. **Uproszczona Obsługa Cen** 💰 (NOWE w v2.3)
- 💵 **Ceny zarządzane przez WooCommerce**: Konfigurator nie wyświetla cen
- ✅ **Brak duplikacji**: Tylko jeden konfigurator na stronie produktu
- 🎯 **Cena wariantu**: WooCommerce pokazuje cenę wybranego wariantu (rozmiar + ramka)
- 🧹 **Czystszy interfejs**: Konfigurator skupia się tylko na konfiguracji canvas

### Główne Usprawnienia v2.1:

#### 1. **Wizualny Wybór Koloru Ramki Canvas**
- 🎨 **10 gotowych kolorów** w formie małych przycisków (40x40px):
  - White (#FFFFFF)
  - Black (#000000)
  - Brown (#8B4513)
  - Gold (#D4AF37)
  - Silver (#C0C0C0)
  - Navy (#1E3A8A)
  - Green (#064E3B)
  - Rust (#7C2D12)
  - Pink (#BE185D)
  - Gray (#4B5563)
- ✓ Checkmark na wybranym kolorze
- 🔍 Tooltips z nazwami kolorów
- 📱 Responsywne (36x36px na mobile)

#### 2. **Minimalistyczne Przyciski Wariantów (Sizes & Frame)**
- ✨ Przezroczyste tło z subtelną ramką
- 🎯 Fioletowa ramka i tło przy hover
- 🔥 2px solid border + fioletowy tekst przy wyborze
- 🌊 Smooth transition (0.3s ease)
- 📐 Border-radius: 8px
- 🎨 Font-weight: 400 → 500 przy wyborze

#### 3. **Zmniejszone Kafelki Canvas Edge (o 30%)**
- 📏 Min-width: 100px (zamiast ~140px)
- 🎴 Padding: 16px (zamiast 24px)
- 🖼️ Ikony: 40px wysokości (zamiast 50px)
- 💫 Brak ciężkich cieni - tylko subtle shadow przy hover
- 🎨 Ikony z gradientami fiolet + turkus

#### 4. **Przycisk "Add to Cart"**
- 🔵 Tło: fioletowy `#773fc6`
- 🌟 Hover: ciemniejszy fiolet `#5f2fa3`
- 📐 Border-radius: 8px
- 🚀 Transform: translateY(-2px) + shadow przy hover

### Design Principles v2.1:

- **Minimalizm**: Dużo białej przestrzeni, czyste linie
- **Subtle**: Delikatne animacje (0.3s transition)
- **Konsystencja**: Fiolet + turkus w całym konfiguratorze
- **Accessibility**: Wyraźne stany hover/active/disabled
- **Mobile-first**: Responsywny design

## 🚀 Instalacja

1. Skopiuj folder `printumo-woocommerce-integration` do katalogu `/wp-content/plugins/`
2. Aktywuj wtyczkę w panelu WordPress
3. Przejdź do **Printumo** → **Settings**
4. Wprowadź klucz API z Printumo
5. Wybierz kategorię dla importowanych produktów
6. Kliknij **Importuj produkty**

## ⚙️ Funkcje

### Automatyczny Import Produktów
- Pobiera wszystkie produkty z Printumo API
- Tworzy warianty WooCommerce (Size + Framing)
- Automatycznie importuje zdjęcia produktów
- Aktualizuje istniejące produkty przy ponownym imporcie

### Konfigurator Canvas (dla produktów canvas)
- **Wyświetlanie przez shortcode**: Użyj `[printumo_configurator]` na stronie produktu
- **3 opcje wykończenia krawędzi**:
  - Mirrored (odbicie lustrzane)
  - Stretched (rozciągnięcie obrazu)
  - Solid Color (jednolity kolor + **wizualny wybór z 10 kolorów**)
- **Wizualne przyciski kolorów** - 10 gotowych kolorów (White, Black, Brown, Gold, Silver, Navy, Green, Rust, Pink, Gray)
- **Ceny zarządzane przez WooCommerce**: Konfigurator nie wyświetla cen, WooCommerce pokazuje cenę wybranego wariantu
- Zapisuje konfigurację w koszyku i zamówieniu
- Automatycznie wysyła konfigurację do Printumo przy składaniu zamówienia

### Automatyczne Wysyłanie Zamówień
- Automatyczna wysyłka do Printumo przy statusie "processing" lub "completed"
- Przekazuje konfigurację canvas wrapping (typ + kolor)
- Zapisuje ID zamówienia Printumo w meta danych

### Synchronizacja Statusów
- Cron co godzinę synchronizuje statusy zamówień
- Mapowanie statusów Printumo → WooCommerce
- Ręczna synchronizacja dostępna w panelu admin

## 🎨 Szczegóły Stylistyczne v2.1

### CSS Variables:
```css
--printumo-primary: #773fc6
--printumo-primary-hover: #5f2fa3
--printumo-primary-light: rgba(119, 63, 198, 0.05)
--printumo-primary-medium: rgba(119, 63, 198, 0.08)
--printumo-secondary: #88d8d3
--printumo-bg: #FFFFFF
--printumo-text: #333333
--printumo-border: #E0E0E0
```

### Transitions:
- **Duration**: 0.3s (wszystkie animacje)
- **Easing**: ease (subtle, naturalny)
- **Transform**: translateY(-2px) przy hover

### Shadows:
- **Default**: none (minimalizm)
- **Hover**: `0 2px 8px rgba(119, 63, 198, 0.15)`
- **Selected**: `0 0 0 2px rgba(119, 63, 198, 0.2)`
- **Button hover**: `0 4px 12px rgba(119, 63, 198, 0.3)`

### Typography:
- **Nagłówki**: uppercase, letter-spacing 0.5px
- **Weights**: 400 (default) → 500 (selected)
- **Sizes**: 14-16px (przyciski), 11px (opisy)

### Border Radius:
- **Przyciski/karty**: 8px (zaokrąglone narożniki)
- **Większe karty**: 12px
- **Checkmark**: 50% (okrągły)

## 📋 Użycie

### Shortcode:
```php
[printumo_configurator]
```
Użyj shortcode `[printumo_configurator]` na stronie produktów canvas (np. w Elementorze) aby wyświetlić konfigurator krawędzi canvas.

### API Integration:
Wtyczka automatycznie wysyła zamówienia do Printumo API z konfiguracją:
```json
{
  "line_items": [
    {
      "variant_id": 456,
      "quantity": 1,
      "canvas_wrapping": {
        "wrap_type": "solid_color",
        "wrap_color": "#8B4513"
      }
    }
  ]
}
```

## 🔧 Konfiguracja

### API Key
Wygeneruj klucz API w swoim profilu Printumo (32-znakowy base64 token)

### Kategoria Produktów
Wybierz kategorię WooCommerce, do której zostaną przypisane importowane produkty

### Auto-wysyłka
Włącz/wyłącz automatyczne wysyłanie zamówień do Printumo

## 📱 Kompatybilność

- ✅ WordPress 5.0+
- ✅ WooCommerce 5.0+
- ✅ PHP 7.4+
- ✅ Wszystkie nowoczesne przeglądarki
- ✅ Mobile & Tablet friendly (responsive breakpoints)

## 🎯 Technologie

- **WordPress Plugin API**
- **WooCommerce Hooks & Filters**
- **jQuery** (dla interakcji)
- **CSS Grid & Flexbox** (responsywny layout)
- **CSS Variables** (konsystentna paleta kolorów)
- **CSS Transitions & Transforms** (subtle animations)
- **Printumo REST API v1**

## 📄 Changelog

### v2.3.1 (2025-11-16)
- 🚫 **Ukryto widget Elementor z zakresem cen**: Dodano CSS ukrywający konkretny widget `.elementor-element-cefb45c`
- 🎯 **Precyzyjne targetowanie**: Ukrywa tylko widget z ID `cefb45c`, nie wpływa na inne ceny w sklepie
- 📝 **Czysty interfejs**: Brak duplikacji zakresu cen na stronie produktu

### v2.3.0 (2025-11-16)
- 🔧 **Naprawiono duplikację konfiguratora**: Usunięto auto-insert hook powodujący podwójne wyświetlanie
- 💰 **Przywrócono ceny WooCommerce**: Usunięto widget ceny z konfiguratora - tylko WooCommerce pokazuje ceny
- 🧹 **Usunięto system dynamicznych cen**: Konfigurator nie wyświetla już dodatków cenowych
- ✅ **Uproszczono konfigurator**: Tylko wybór wykończenia krawędzi i koloru ramki
- 🎯 **Poprawiono logikę cenową**: WooCommerce zarządza wszystkimi cenami wariantów
- 📊 **Czystszy kod**: Usunięto nieużywany kod JavaScript i CSS dla cen

### v2.2.2 (2025-11-16)
- 🔧 **Naprawiono wyświetlanie konfiguratora**: Automatyczne wstawianie na stronę produktu
- ✅ **Auto-insert**: Konfigurator pojawia się automatycznie bez potrzeby shortcode
- 🎯 **Hook WooCommerce**: Używa `woocommerce_before_add_to_cart_button`
- 🐛 **Bugfix**: Rozwiązano problem z niewyświetlaniem się konfiguratora v2.2
- 📊 **Dodano debugowanie**: Metoda auto_display_configurator() z logowaniem

### v2.2.1 (2025-11-16)
- 🐛 **Debugowanie**: Dodano comprehensive logging system
- 🔍 **Console logs**: JavaScript debugging dla price calculation
- 🛠️ **PHP logs**: error_log() dla diagnostyki backend
- 📝 **HTML markers**: Znaczniki do weryfikacji renderowania

### v2.2.0 (2025-11-16)
- 💰 **System dynamicznych cen**: Automatyczne przeliczanie ceny przy zmianie opcji
- 📊 **Widget ceny**: Duża, fioletowa cena pod konfiguratorem (36px, pogrubiona)
- 💵 **Dodatki cenowe**: Wyświetlanie przy każdej opcji:
  - Mirrored: Base price (+0 €)
  - Stretched: +15 €
  - Solid Color: +10 €
- 🚫 **Ukryta domyślna cena WooCommerce**: Zakres cen zastąpiony pojedynczą ceną
- ⚡ **Real-time obliczenia**: JavaScript automatycznie aktualizuje cenę
- 🎨 **Responsive**: Widget ceny dostosowuje się do mobile (28px)

### v2.1.0 (2025-11-16)
- 🎨 **Nowa paleta kolorów**: Fiolet (#773fc6) + Turkus (#88d8d3)
- 🎨 **Wizualny wybór koloru ramki**: 10 kolorów w formie małych przycisków
- 📐 **Zmniejszone kafelki o 30%**: Min-width 100px, ikony 40px
- ✨ **Minimalistyczny design**: Przezroczyste tła, subtle shadows
- 🔵 **Nowy przycisk Add to Cart**: Fioletowy z hover effects
- 🌊 **Smooth transitions**: 0.3s ease dla wszystkich animacji
- ♿ **Ulepszona accessibility**: Wyraźne stany hover/active
- 📱 **Lepsza responsywność**: Breakpointy dla mobile/tablet

### v2.0.0 (2025-11-16)
- 🎨 Przeprojektowany konfigurator canvas
- ✨ Dodano profesjonalne animacje i przejścia
- 🎴 Karty z ikonami gradientowymi
- 🎯 Checkmark dla wybranej opcji
- 📱 Ulepszona responsywność

### v1.8.2
- Podstawowa funkcjonalność integracji z Printumo
- Konfigurator canvas (wersja podstawowa)
- Import produktów i synchronizacja zamówień

## 👨‍💻 Autor

**MaxDigital.pl**

## 📞 Wsparcie

Problemy z API? Kontakt: dev@printumo.com

---

**Made with ❤️ for beautiful print-on-demand stores**

*Design inspirowany minimalizmem i eleganc ją.*
