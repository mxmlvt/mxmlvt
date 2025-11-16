# Printumo WooCommerce Integration v2.0

Profesjonalna wtyczka integrująca WooCommerce z Printumo API - z eleganckim, nowoczesnym konfiguratorem produktów canvas.

## 🎨 Nowy Profesjonalny Design

### Główne Usprawnienia Wizualne:

#### 1. **Przyciski Wyboru Wariantów**
- ✨ Nowoczesne karty z animacjami hover
- 🎯 Wyraźne wskazanie wybranej opcji (gradient niebieski)
- 🌊 Płynne przejścia i animacje shimmer
- 📱 Pełna responsywność
- ♿ Wyłączone opcje z przejrzystością 30%

#### 2. **Konfigurator Canvas Edge**
- 🎴 Karty w układzie grid (3 kolumny, responsywne)
- 🎨 Każda opcja ma własną ikonę gradientową:
  - **Mirrored**: Gradient fioletowy (mirror effect)
  - **Stretched**: Gradient różowo-czerwony
  - **Solid Color**: Gradient niebieski z obramowaniem
- ✅ Checkmark w prawym górnym rogu dla wybranej opcji
- 📊 Kolorowy pasek na górze aktywnej karty
- 🌟 Shadow effects i hover animations
- 📝 Szczegółowe opisy dla każdej opcji

#### 3. **Color Picker**
- 🎨 Zintegrowany WordPress Color Picker
- 🎭 Animowane wyświetlanie (slideDown)
- 🖼️ Eleganckie tło gradientowe
- 🎯 Wyraźna wizualizacja wybranego koloru

### Design Principles:

- **Minimalistyczny**: Czysty, nowoczesny design bez przeładowania
- **Intuicyjny**: Łatwy w użyciu, jasne wskazówki wizualne
- **Profesjonalny**: Wysokiej jakości komponenty UI
- **Responsywny**: Doskonale wygląda na wszystkich urządzeniach
- **Accessible**: Wyraźne stany dla disabled/hover/active

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
- **3 opcje wykończenia krawędzi**:
  - Mirrored (odbicie lustrzane)
  - Stretched (rozciągnięcie obrazu)
  - Solid Color (jednolity kolor + wybór koloru)
- Zapisuje konfigurację w koszyku i zamówieniu
- Automatycznie wysyła konfigurację do Printumo przy składaniu zamówienia

### Automatyczne Wysyłanie Zamówień
- Automatyczna wysyłka do Printumo przy statusie "processing" lub "completed"
- Przekazuje konfigurację canvas wrapping
- Zapisuje ID zamówienia Printumo w meta danych

### Synchronizacja Statusów
- Cron co godzinę synchronizuje statusy zamówień
- Mapowanie statusów Printumo → WooCommerce
- Ręczna synchronizacja dostępna w panelu admin

## 🎨 Szczegóły Stylistyczne

### Kolory:
- **Główny**: `#3182ce` (niebieski)
- **Gradient**: `#3182ce → #2c5aa0`
- **Tło**: `#f7fafc → #edf2f7`
- **Tekst**: `#1a202c`, `#2d3748`, `#718096`
- **Border**: `#e8e8e8`, `#cbd5e0`

### Animacje:
- **Hover Transform**: `translateY(-2px)` / `translateY(-3px)`
- **Transition**: `cubic-bezier(0.4, 0, 0.2, 1)` (material design)
- **Shimmer Effect**: Gradient animation przy hover
- **SlideDown**: Dla color picker (0.3s ease)

### Responsywność:
- **Desktop**: Grid 3-kolumnowy, pełne przyciski
- **Tablet**: Grid auto-fit, min 180px
- **Mobile**: Pojedyncza kolumna, zmniejszone fonty i padding

### Shadow Effects:
- **Default**: `0 2px 8px rgba(0,0,0,0.04)`
- **Hover**: `0 8px 24px rgba(0,0,0,0.12)`
- **Active**: `0 8px 24px rgba(49, 130, 206, 0.15)`
- **Button**: `0 4px 15px rgba(49, 130, 206, 0.4)`

## 📋 Użycie CSS z !important

Wszystkie style używają flagi `!important` aby zapewnić priorytet nad stylami motywu i innych wtyczek.

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
- ✅ Mobile & Tablet friendly

## 🎯 Technologie

- **WordPress Plugin API**
- **WooCommerce Hooks & Filters**
- **jQuery** (dla interakcji)
- **WordPress Color Picker**
- **CSS Grid & Flexbox**
- **CSS Animations & Transitions**
- **Printumo REST API v1**

## 📄 Changelog

### v2.0.0 (2025-11-16)
- 🎨 Całkowicie przeprojektowany konfigurator canvas
- ✨ Dodano profesjonalne animacje i przejścia
- 🎴 Karty z ikonami gradientowymi dla opcji wrapping
- 🎯 Checkmark dla wybranej opcji
- 📱 Ulepszona responsywność
- 🌟 Shadow effects i hover states
- 🎨 Lepszy color picker z animacjami
- ♿ Ulepszona accessibility

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
