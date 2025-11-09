# WooCommerce Dynamic Grid Variations

Rozwiązanie dla WordPress/WooCommerce + JetElements/Elementor, które umożliwia dynamiczne wyświetlanie wariantów produktów w gridach z automatycznym przekierowaniem na stronę wariantu po dodaniu do koszyka.

## 🎯 Funkcjonalność

### Główne możliwości:
- ✅ **Auto-matching wariantów z kategoriami** - automatyczne dopasowywanie wariantów produktów do odpowiednich kategorii
- ✅ **Dynamiczne filtrowanie** - pokazywanie tylko produktów z odpowiednimi wariantami w danej kategorii
- ✅ **Inteligentne ceny** - wyświetlanie ceny konkretnego wariantu zamiast zakresu cen
- ✅ **Dodawanie do koszyka** - bezpośrednie dodawanie wariantów z grida
- ✅ **Przekierowanie na stronę wariantu** - automatyczne przekierowanie na stronę produktu z wybranym wariantem po dodaniu do koszyka
- ✅ **Obsługa AJAX** - pełna obsługa AJAX add to cart z przekierowaniem
- ✅ **Poprawne linki** - linki w gridzie prowadzą bezpośrednio do strony z wybranym wariantem

## 📋 Wymagania

- WordPress 5.0+
- WooCommerce 3.0+
- JetWooBuilder / JetElements (Elementor)
- Produkty zmienne (Variable Products)

## 🔧 Instalacja

### Metoda 1: Child Theme (Zalecana)

1. **Skopiuj kod do pliku `functions.php` swojego child theme:**
   ```
   wp-content/themes/twoj-child-theme/functions.php
   ```

2. **Wklej cały kod z pliku `functions.php`**

3. **Zapisz plik i wyczyść cache WordPress (jeśli używasz)**

### Metoda 2: Plugin Custom Code

1. **Zainstaluj plugin do custom code** (np. Code Snippets, WPCode)

2. **Utwórz nowy snippet i wklej kod z `functions.php`**

3. **Aktywuj snippet**

## ⚙️ Konfiguracja

### Mapowanie wariantów na kategorie

W funkcji `get_variation_category_mapping()` zdefiniuj mapowanie nazw wariantów na slugi kategorii:

```php
function get_variation_category_mapping() {
    return array(
        'original' => 'originals',
        'Original' => 'originals',
        'canvas print' => 'canvas-prints',
        'Canvas Print' => 'canvas-prints',
        'paper print' => 'paper-prints',
        'Paper Print' => 'paper-prints',
        // Dodaj więcej mapowań...
    );
}
```

**Przykład:**
- Wariant o nazwie `"Original"` lub `"original"` → kategoria `originals`
- Wariant `"Canvas Print"` → kategoria `canvas-prints`

### Struktura produktów

1. **Utwórz produkt zmienny (Variable Product)**
2. **Dodaj atrybuty** (np. "Type" z wartościami: Original, Canvas Print, Paper Print)
3. **Utwórz warianty** dla każdego typu
4. **Przypisz kategorie** zgodnie z mapowaniem (originals, canvas-prints, paper-prints)

## 🎨 Jak to działa

### W gridzie kategorii (np. `/category/originals/`)
1. Pokazywane są tylko produkty, które mają wariant pasujący do tej kategorii
2. Cena wyświetlana to cena konkretnego wariantu (np. Original)
3. Przycisk "Add to cart" dodaje ten konkretny wariant
4. Po kliknięciu produktu/dodaniu do koszyka → przekierowanie na stronę produktu z wybranym wariantem

### Na stronie głównej / innych stronach
1. Domyślnie pokazywany jest wariant "Original"
2. Cena wyświetlana to cena wariantu Original
3. Po kliknięciu → przekierowanie na stronę produktu z wariantem Original

### Proces dodawania do koszyka
1. **Użytkownik klika "Add to cart"** w gridzie
2. **Produkt (wariant) dodaje się do koszyka** (AJAX)
3. **Automatyczne przekierowanie** na stronę produktu z wybranym wariantem
4. **Użytkownik widzi pełne informacje** o produkcie i może kontynuować zakupy lub zmienić wariant

## 🔍 Funkcje techniczne

### Dodane funkcje:

| Funkcja | Opis |
|---------|------|
| `get_variation_category_mapping()` | Mapowanie wariantów na kategorie |
| `get_category_for_variation_auto()` | Pobiera kategorię dla wariantu (cache) |
| `filter_products_by_variation_auto()` | Filtruje produkty w archiwach kategorii |
| `get_variation_for_current_category()` | Pobiera wariant dla aktualnej kategorii |
| `get_original_variation_id()` | Pobiera wariant "Original" dla produktu |
| `show_variation_price_auto()` | Podmienia cenę na cenę wariantu |
| `variation_add_to_cart_url_auto()` | Zmienia URL add to cart |
| `variation_add_to_cart_text_auto()` | Zmienia tekst przycisku |
| `fix_variation_ajax_add_to_cart_auto()` | Fix dla AJAX add to cart |
| `variation_product_link_auto()` | Podmienia link do produktu |
| `byku_jet_fix_grid_category_display()` | Fix kategorii dla JetWooBuilder |
| `redirect_to_variation_page_after_add_to_cart()` | **NOWE** - Przekierowanie po add to cart |
| `variation_ajax_add_to_cart_redirect_script()` | **NOWE** - JavaScript dla AJAX + przekierowanie |
| `ajax_add_variation_to_cart()` | **NOWE** - Handler AJAX add to cart |

### Cache i optymalizacja:

- ✅ **Transient cache** dla list produktów (1 godzina)
- ✅ **Static cache** dla wariantów i kategorii
- ✅ **Automatyczne czyszczenie cache** po zapisaniu produktu

## 🐛 Rozwiązywanie problemów

### Produkty nie pokazują się w kategorii
- Sprawdź mapowanie wariantów w `get_variation_category_mapping()`
- Upewnij się, że warianty mają poprawne atrybuty
- Wyczyść cache (usuń transienty z bazy danych)

### Przekierowanie nie działa
- Sprawdź czy w WooCommerce nie jest włączone przekierowanie do koszyka po dodaniu produktu
- Upewnij się, że JavaScript nie jest blokowany
- Sprawdź konsolę przeglądarki pod kątem błędów

### Cena nie zmienia się
- Sprawdź czy produkt jest typu "Variable"
- Upewnij się, że warianty mają przypisane ceny
- Wyczyść cache motywu/pluginów

## 📝 Changelog

### v1.1 - 2025-11-09
- ✨ Dodano automatyczne przekierowanie na stronę wariantu po dodaniu do koszyka
- ✨ Dodano obsługę AJAX add to cart z przekierowaniem
- ✨ Dodano atrybut `data-product_url` do przycisku add to cart
- ✨ Dodano JavaScript handler dla przekierowań
- 🐛 Fix: Poprawiono obsługę AJAX request

### v1.0
- 🎉 Pierwsza wersja
- ✅ Auto-matching wariantów z kategoriami
- ✅ Dynamiczne filtrowanie produktów
- ✅ Inteligentne ceny i linki

## 📞 Wsparcie

W przypadku problemów:
1. Sprawdź logi WordPress (Debug Log)
2. Sprawdź konsolę przeglądarki
3. Upewnij się, że wszystkie wymagania są spełnione

## 📄 Licencja

Kod dostępny do użytku w projektach WordPress/WooCommerce.

---

**Autor:** mxmlvt
**Wersja:** 1.1
**Data:** 2025-11-09
