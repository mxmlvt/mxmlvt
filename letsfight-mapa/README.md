# Let's Fight - Mapa Klubów 🥊

Profesjonalna wtyczka WordPress do wyświetlania klubów sportów walki z integracją **JetEngine** i **JetSmartFilters** oraz interaktywną mapą **Mapbox GL JS**.

![Version](https://img.shields.io/badge/version-1.1.2-orange)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)

---

## ✨ Funkcjonalności

### 🎯 Główne możliwości

- ✅ **Panel wyszukiwania** z filtrami (miasto + dyscyplina)
- ✅ **Automatyczne wykrywanie miasta** z URL
- ✅ **Toggle Lista/Mapa** z płynnym przełączaniem
- ✅ **Live counter** liczby klubów
- ✅ **Interaktywna mapa Mapbox** z markerami
- ✅ **Automatyczne geokodowanie** adresów
- ✅ **Kliknięcie markera** → scroll + highlight karty
- ✅ **Synchronizacja z JetEngine AJAX**
- ✅ **Responsywny design** mobile-first
- ✅ **Kolory brandowe** #f79716 wszędzie
- ✅ **Dark mode styling**
- ✅ **Custom SVG markery** w kolorze Let's Fight
- ✅ **Popupy z informacjami** o klubach
- ✅ **Cache geokodowania** (30 dni)
- ✅ **Obsługa błędów** i walidacja

---

## 📋 Wymagania

### Obowiązkowe:
- WordPress **5.0+**
- PHP **7.4+**
- **JetEngine** (Crocoblock)
- **JetSmartFilters** (Crocoblock)

### Opcjonalne:
- **Mapbox API token** (darmowy plan)

---

## 🚀 Instalacja

### Krok 1: Upload wtyczki

```bash
# Skopiuj folder do katalogu wtyczek
wp-content/plugins/letsfight-mapa/
```

### Krok 2: Aktywacja

1. Przejdź do **Wtyczki** w panelu WordPress
2. Znajdź **Let's Fight - Mapa Klubów**
3. Kliknij **Aktywuj**

⚠️ **Uwaga:** Wtyczka sprawdzi czy JetEngine i JetSmartFilters są aktywne. Jeśli nie, zobaczysz komunikat błędu.

### Krok 3: Konfiguracja Mapbox (opcjonalne)

1. Zarejestruj się na [Mapbox.com](https://www.mapbox.com/)
2. Stwórz darmowy token API
3. Wklej token w pliku `letsfight-mapa.php`:

```php
private $mapbox_token = 'TWÓJ_TOKEN_TUTAJ';
```

---

## 📝 Użycie

### Shortcode podstawowy

```
[letsfight_wyszukiwarka_miasto]
```

### Shortcode z parametrami

```
[letsfight_wyszukiwarka_miasto listing_id="439" filter_dyscyplina="467" filter_search="516"]
```

### Parametry:

| Parametr | Typ | Domyślnie | Opis |
|----------|-----|-----------|------|
| `listing_id` | int | `439` | ID listingu JetEngine |
| `filter_dyscyplina` | int | `467` | ID filtra dyscypliny (JetSmartFilters) |
| `filter_search` | int | `516` | ID filtra wyszukiwania (JetSmartFilters) |

### Alias shortcode

```
[letsfight_club_panel]
```

---

## 🏗️ Struktura plików

```
letsfight-mapa/
├── letsfight-mapa.php          # Główny plik wtyczki
├── README.md                    # Dokumentacja
└── assets/
    ├── style.css               # Stylizacja (dark mode, responsive)
    └── script.js               # JavaScript (Mapbox, AJAX, UI)
```

---

## ⚙️ Konfiguracja

### 1. Custom Post Type: `kluby`

Wtyczka zakłada, że masz CPT o nazwie **`kluby`** z następującymi polami:

| Pole | Typ | Meta Key | Opis |
|------|-----|----------|------|
| Adres | Text | `adres` | Adres klubu (do geokodowania) |
| Szerokość geog. | Number | `club_latitude` | Auto-generowane |
| Długość geog. | Number | `club_longitude` | Auto-generowane |

### 2. Taxonomia: `miasta`

Stwórz taksonomię **`miasta`** dla CPT `kluby`:

```php
// Przykład rejestracji
register_taxonomy('miasta', 'kluby', [
    'label' => 'Miasta',
    'hierarchical' => true,
    'rewrite' => ['slug' => 'miasto'],
]);
```

### 3. JetEngine Listing

Stwórz **Listing Grid** w JetEngine:
- Typ: Grid
- Source: Query (Custom Post Type: kluby)
- Layout: Twój custom design

### 4. JetSmartFilters

Stwórz dwa filtry:

1. **Filtr Dyscypliny** (Select)
   - Taxonomy: `dyscypliny`
   - Apply type: AJAX

2. **Wyszukiwarka** (Search)
   - Search by: Post title
   - Apply type: AJAX

---

## 🗺️ Jak to działa

### Wykrywanie miasta z URL

Wtyczka automatycznie wykrywa miasto z URL:

```
https://letsfight.pl/trenuj/gdansk/
                          ^^^^^^
                          slug miasta
```

### Geokodowanie adresów

1. Przy pierwszym załadowaniu mapy, wtyczka pobiera adresy klubów
2. Jeśli brak współrzędnych (lat/lng) → wywołuje API Mapbox Geocoding
3. Zapisuje współrzędne w meta polach `club_latitude` i `club_longitude`
4. **Cache:** Wynik geokodowania zapisywany jest w transientach na 30 dni

### Synchronizacja z JetEngine

```javascript
// Event wywoływany przez JetSmartFilters
$(document).on('jet-filter-content-rendered', function() {
    updateCounter();
    updateMapMarkers();
});
```

### Kliknięcie markera

1. Kliknięcie markera na mapie
2. Przełączenie na widok **Lista**
3. Scroll do odpowiedniej karty klubu
4. Highlight animacja (pulsujący cień)

---

## 🎨 Customizacja

### Zmiana kolorów

Edytuj `assets/style.css`:

```css
/* Główny kolor brandowy */
#f79716  → Twój kolor

/* Dark background */
#1a1a1a  → Twój kolor

/* Secondary color */
#ff0080  → Twój kolor
```

### Zmiana markera

Edytuj SVG w `assets/script.js` (funkcja `createMarkerElement()`):

```javascript
el.style.backgroundImage = 'url(data:image/svg+xml;base64,...)';
```

### Zmiana stylu mapy

Edytuj w `assets/script.js`:

```javascript
map = new mapboxgl.Map({
    style: 'mapbox://styles/mapbox/dark-v11',  // Zmień na: streets-v11, light-v10, etc.
    zoom: 6,  // Zoom początkowy
});
```

---

## 🔧 API Reference

### PHP

#### `register_meta_fields()`
Rejestruje pola meta dla współrzędnych klubów.

#### `render_wyszukiwarka($atts)`
Renderuje shortcode z panelem wyszukiwania.

**Parametry:**
- `$atts` (array) - Atrybuty shortcode

**Zwraca:** string (HTML)

#### `ajax_get_clubs_coords()`
Handler AJAX zwracający współrzędne klubów.

**POST params:**
- `post_ids` (array) - Tablica ID postów
- `nonce` (string) - Security nonce

**Zwraca:** JSON

#### `geocode_address($address)`
Geokoduje adres przez API Mapbox.

**Parametry:**
- `$address` (string) - Adres do geokodowania

**Zwraca:** array `['lat' => float, 'lng' => float]` lub `null`

### JavaScript

#### `initWyszukiwarka()`
Inicjalizuje wszystkie komponenty UI.

#### `initMap()`
Inicjalizuje mapę Mapbox GL.

#### `updateMapMarkers()`
Aktualizuje markery na mapie (wywołuje AJAX).

#### `scrollToClub(postId)`
Przewija do karty klubu i ją podświetla.

**Parametry:**
- `postId` (int) - ID posta klubu

---

## 🐛 Rozwiązywanie problemów

### Błąd: "Wymagane pluginy nie są aktywne"

**Rozwiązanie:**
1. Zainstaluj **JetEngine**
2. Zainstaluj **JetSmartFilters**
3. Aktywuj oba pluginy
4. Odśwież wtyczkę

### Mapa się nie ładuje

**Możliwe przyczyny:**
- ❌ Brak tokenu Mapbox
- ❌ Nieprawidłowy token
- ❌ Blokada JavaScript

**Rozwiązanie:**
1. Sprawdź konsolę przeglądarki (F12)
2. Sprawdź czy token jest prawidłowy
3. Wyczyść cache przeglądarki

### Kluby nie pokazują się na mapie

**Możliwe przyczyny:**
- ❌ Brak pola `adres` w postach
- ❌ Błąd geokodowania
- ❌ Limit API Mapbox

**Rozwiązanie:**
1. Sprawdź czy posty mają wypełnione pole `adres`
2. Sprawdź logi błędów WordPress
3. Sprawdź limit API na Mapbox.com

### Filtry nie działają

**Rozwiązanie:**
1. Sprawdź ID filtrów w shortcode
2. Upewnij się że query_id jest `default`
3. Sprawdź czy JetSmartFilters są skonfigurowane dla tego providera

---

## 📊 Performance

### Optymalizacje:

- ✅ **Lazy loading mapy** - inicjalizacja tylko przy przełączeniu
- ✅ **Cache geokodowania** - 30 dni w transientach
- ✅ **Warunkowo ładowane assety** - tylko na stronach z shortcodem
- ✅ **AJAX pagination** - bez przeładowania strony
- ✅ **Debouncing** - dla eventów scroll i resize

### Metryki:

| Metryka | Wartość |
|---------|---------|
| CSS | ~15 KB |
| JavaScript | ~12 KB |
| Mapbox GL CSS | ~35 KB |
| Mapbox GL JS | ~450 KB |

---

## 🔐 Bezpieczeństwo

### Implementowane zabezpieczenia:

- ✅ **Nonce verification** dla AJAX
- ✅ **Sanitization** wszystkich inputów
- ✅ **Escaping** wszystkich outputów
- ✅ **XSS protection** w popup HTML
- ✅ **SQL injection** - używamy WP Query
- ✅ **ABSPATH check**

---

## 📱 Responsywność

### Breakpointy:

| Breakpoint | Ekran | Zmiany |
|------------|-------|--------|
| 1024px | Tablet | Mniejsze paddingi |
| 768px | Mobile | Kolumny pionowe, pełna szerokość |
| 480px | Small mobile | Ukrycie tekstów, tylko ikony |

### Mobile-first features:

- ✅ Touch-friendly przyciski (min 44x44px)
- ✅ Responsywna mapa (450px → 350px)
- ✅ Stackowane filtry
- ✅ Ukryte etykiety na małych ekranach

---

## 🧪 Testowanie

### Checklist testowy:

- [ ] Instalacja wtyczki bez błędów
- [ ] Shortcode renderuje się poprawnie
- [ ] Filtry JetSmartFilters działają
- [ ] Mapa ładuje się po kliknięciu toggle
- [ ] Markery pokazują się na mapie
- [ ] Popup otwiera się po kliknięciu markera
- [ ] Kliknięcie markera przewija do karty
- [ ] Counter aktualizuje się po filtracji
- [ ] Zmiana miasta pokazuje przycisk "Szukaj"
- [ ] Przycisk "Szukaj" przekierowuje do miasta
- [ ] Responsywność na mobile/tablet
- [ ] Brak błędów w konsoli

---

## 🆘 Wsparcie

### Zgłaszanie błędów:

1. Sprawdź **Rozwiązywanie problemów** powyżej
2. Sprawdź logi błędów WordPress
3. Otwórz issue na GitHub: [github.com/mxmlvt/mapa](https://github.com/mxmlvt/mapa)

### Debug mode:

Włącz debug w `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Po włączeniu debug mode:
- **Panel debug na dole strony** - wyświetla wszystkie logi w czasie rzeczywistym
- **Textarea do kopiowania** - możesz skopiować cały log i przesłać w zgłoszeniu błędu
- **Sprawdzanie shortcode'ów** - pokazuje czy JetSmartFilters i JetEngine są zarejestrowane
- **Logi taxonomii** - wyświetla dokładnie co zwraca `get_terms()` dla miast
- **Timestamp każdego wpisu** - dokładny czas każdej operacji

Wtyczka automatycznie włączy verbose logging w konsoli i wyświetli panel debug na dole strony frontendu.

---

## 📜 Changelog

### v1.1.2 (2025-11-21)
- ✨ **Skanowanie dostępnych taksonomii dla CPT 'kluby'** - pokazuje wszystkie zarejestrowane taksonomie
- ✨ **Lista wszystkich shortcodów zaczynających się od "jet"** - wykrywa faktyczne nazwy shortcodów
- 🐛 Debug pomaga znaleźć prawidłową nazwę taksonomii miasta
- 🐛 Debug pomaga zidentyfikować dlaczego JetSmartFilters shortcodes nie są dostępne

### v1.1.1 (2025-11-21)
- ✨ **Dodano zaawansowany debug mode** - widoczny panel na dole strony z pełnym logiem
- ✨ **Poprawiono ładowanie shortcode'ów** - zmieniono priorytet rejestracji na 999
- ✨ **Zawsze ładuj assety na froncie** - fix dla dynamicznych shortcode'ów
- ✨ **Sprawdzanie czy shortcody są zarejestrowane** - wykrywa problemy z JetSmartFilters
- ✨ **Debug log dla taksonomii miast** - pokazuje dokładnie co zwraca get_terms()
- ✨ **Kopiowalne logi debug** - textarea z logiem do wklejenia w zgłoszeniu błędu
- 🐛 Fix dla pustego dropdown miasta - dodano sprawdzanie błędów get_terms()
- 🐛 Poprawiono wykrywanie błędów WP_Error przy pobieraniu taxonomii

### v1.1.0 (2025-11-21)
- ✨ Kompletna przebudowa kodu PHP
- ✨ Dodano singleton pattern
- ✨ Dodano sprawdzanie wymagań przy aktywacji
- ✨ Poprawiono obsługę błędów
- ✨ Dodano cache dla geokodowania
- ✨ Przepisano JavaScript z lepszą obsługą błędów
- ✨ Dodano debug mode
- ✨ Poprawiono responsywność
- ✨ Dodano dokumentację README
- 🐛 Naprawiono błąd krytyczny WordPress
- 🐛 Poprawiono składnię shortcode JetEngine
- 🐛 Fix dla ładowania JetSmartFilters assets

### v1.0.2 (poprzednia wersja)
- Pierwsza wersja robocza

---

## 📄 Licencja

Ten kod jest dostępny dla projektów WordPress. Autor: **MaxDigital.pl**

---

## 👨‍💻 Autor

**mxmlvt** - [GitHub](https://github.com/mxmlvt)

Wtyczka stworzona dla **Let's Fight** - wyszukiwarki klubów sportów walki.

---

## 🙏 Podziękowania

- **Mapbox** - za świetne API map
- **Crocoblock** - za JetEngine i JetSmartFilters
- **Let's Fight** - za zaufanie i projekt

---

**Made with 🥊 for Let's Fight**
