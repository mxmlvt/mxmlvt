<?php
/**
 * Plugin Name: Let's Fight - Mapa Klubów
 * Description: Integracja mapy Mapbox z JetEngine dla klubów sportowych
 * Version: 1.2.1
 * Author: MaxDigital.pl
 * Text Domain: letsfight-mapa
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

/**
 * Główna klasa wtyczki Let's Fight Mapa
 */
class LetsFight_Mapa {

    /**
     * Token API Mapbox
     * @var string
     */
    private $mapbox_token = 'pk.eyJ1IjoibWF4ZGlnaXRhbCIsImEiOiJjbTN4eWc4dXowMDJqMmpzYnZ6dXNsYnNyIn0.vKhEBdS_KwATxsA5fTqmqg';

    /**
     * Wersja wtyczki
     * @var string
     */
    private $version = '1.2.1';

    /**
     * Debug mode
     * @var bool
     */
    private $debug = false;

    /**
     * Singleton instance
     * @var LetsFight_Mapa
     */
    private static $instance = null;

    /**
     * Pobierz instancję singletona
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor - rejestracja hooków
     */
    public function __construct() {
        // Debug mode
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;

        // Sprawdź wymagane pluginy przy aktywacji
        register_activation_hook(__FILE__, [$this, 'check_requirements']);

        // Inicjalizacja
        add_action('init', [$this, 'register_meta_fields']);
        add_action('admin_notices', [$this, 'admin_notices']);

        // Shortcode - priorytet 999 żeby JetEngine i JetSmartFilters załadowały się wcześniej
        add_action('init', function() {
            add_shortcode('letsfight_wyszukiwarka_miasto', [$this, 'render_wyszukiwarka']);
            add_shortcode('letsfight_club_panel', [$this, 'render_wyszukiwarka']); // Alias
        }, 999);

        // AJAX
        add_action('wp_ajax_letsfight_get_clubs_coords', [$this, 'ajax_get_clubs_coords']);
        add_action('wp_ajax_nopriv_letsfight_get_clubs_coords', [$this, 'ajax_get_clubs_coords']);

        // Assets - ładuj zawsze na froncie
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 999);

        // Debug footer
        if ($this->debug && !is_admin()) {
            add_action('wp_footer', [$this, 'output_debug_info'], 999);
        }
    }

    /**
     * Sprawdź wymagania przy aktywacji
     */
    public function check_requirements() {
        $errors = [];

        if (!class_exists('Jet_Engine')) {
            $errors[] = 'JetEngine nie jest zainstalowany lub aktywny.';
        }

        // JetSmartFilters już nie jest wymagany - mamy własne filtry!

        if (!empty($errors)) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                '<h1>Błąd aktywacji wtyczki Let\'s Fight - Mapa Klubów</h1>' .
                '<p>Wymagane pluginy:</p><ul><li>' . implode('</li><li>', $errors) . '</li></ul>' .
                '<p><a href="' . admin_url('plugins.php') . '">Powrót do listy wtyczek</a></p>'
            );
        }
    }

    /**
     * Pokaż powiadomienia w panelu admina
     */
    public function admin_notices() {
        if (!class_exists('Jet_Engine')) {
            ?>
            <div class="notice notice-error">
                <p><strong>Let's Fight - Mapa Klubów:</strong> Wymagany plugin JetEngine nie jest aktywny.</p>
            </div>
            <?php
        }
    }

    /**
     * Rejestracja pól meta dla współrzędnych
     */
    public function register_meta_fields() {
        register_post_meta('kluby', 'club_latitude', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_post_meta('kluby', 'club_longitude', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    /**
     * Załaduj assety (CSS, JS)
     */
    public function enqueue_assets() {
        // Ładuj zawsze na froncie (shortcode może być wywoływany przez JetEngine)
        if (is_admin()) {
            return;
        }

        // Mapbox GL JS
        wp_enqueue_style('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', [], '2.15.0');
        wp_enqueue_script('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', [], '2.15.0', true);

        // Plugin CSS & JS
        wp_enqueue_style(
            'letsfight-mapa',
            plugins_url('assets/style.css', __FILE__),
            [],
            $this->version
        );

        wp_enqueue_script(
            'letsfight-mapa',
            plugins_url('assets/script.js', __FILE__),
            ['jquery', 'mapbox-gl'],
            $this->version,
            true
        );

        // Lokalizacja zmiennych JS
        wp_localize_script('letsfight-mapa', 'letsfightMap', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'mapboxToken' => $this->mapbox_token,
            'nonce' => wp_create_nonce('letsfight_map_nonce'),
            'debug' => $this->debug,
        ]);
    }

    /**
     * Renderowanie shortcode'a wyszukiwarki
     */
    public function render_wyszukiwarka($atts) {
        // Debug: log shortcode call
        $this->debug_log('Wywołanie shortcode letsfight_wyszukiwarka_miasto');

        // Sprawdź wymagane pluginy (tylko JetEngine jest wymagany)
        if (!class_exists('Jet_Engine')) {
            $this->debug_log('BŁĄD: Brak wymaganego pluginu', [
                'Jet_Engine' => 'BRAK',
            ]);
            return '<div class="letsfight-error" style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 8px;">
                <strong>Błąd:</strong> Wymagany plugin JetEngine nie jest aktywny.
            </div>';
        }

        $this->debug_log('Sprawdzanie pluginów', [
            'Jet_Engine' => 'OK',
            'Jet_Smart_Filters' => class_exists('Jet_Smart_Filters') ? 'OK (opcjonalny)' : 'BRAK (używamy własnych filtrów)',
        ]);

        // Parametry shortcode
        $atts = shortcode_atts([
            'listing_id' => '439',
            'filter_dyscyplina' => '467',
            'filter_search' => '516',
        ], $atts);

        $this->debug_log('Parametry shortcode', $atts);

        // Wykryj miasto z URL
        $miasto_slug = $this->get_city_from_url();
        $this->debug_log('Wykryte miasto z URL', $miasto_slug ?: 'brak');

        // Pobierz listę miast (POPRAWIONA NAZWA: miasto, nie miasta!)
        $miasta = get_terms([
            'taxonomy' => 'miasto',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        // Debug: miasta
        if (is_wp_error($miasta)) {
            $this->debug_log('BŁĄD: get_terms zwróciło WP_Error', $miasta->get_error_message());
            $miasta = [];
        } else {
            $this->debug_log('Pobrano miast', [
                'count' => count($miasta),
                'miasta' => array_map(function($m) { return $m->name . ' (' . $m->slug . ')'; }, $miasta),
            ]);
        }

        // Pobierz listę dyscyplin
        $dyscypliny = get_terms([
            'taxonomy' => 'dyscypliny',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        // Debug: dyscypliny
        if (is_wp_error($dyscypliny)) {
            $this->debug_log('BŁĄD: get_terms dla dyscyplin zwróciło WP_Error', $dyscypliny->get_error_message());
            $dyscypliny = [];
        } else {
            $this->debug_log('Pobrano dyscyplin', [
                'count' => count($dyscypliny),
                'dyscypliny' => array_map(function($d) { return $d->name . ' (' . $d->slug . ')'; }, $dyscypliny),
            ]);
        }

        // Skanuj dostępne taksonomie dla CPT 'kluby'
        $kluby_taxonomies = get_object_taxonomies('kluby', 'objects');
        $this->debug_log('Taksonomie zarejestrowane dla CPT \'kluby\'', [
            'count' => count($kluby_taxonomies),
            'nazwy' => array_keys($kluby_taxonomies),
            'szczegóły' => array_map(function($tax) {
                return $tax->label . ' (slug: ' . $tax->name . ')';
            }, $kluby_taxonomies),
        ]);

        // Sprawdź czy shortcody JetSmartFilters są zarejestrowane
        global $shortcode_tags;
        $this->debug_log('Sprawdzanie shortcode\'ów JetSmartFilters', [
            'jet-smart-filters-select' => isset($shortcode_tags['jet-smart-filters-select']) ? 'ZAREJESTROWANY' : 'BRAK',
            'jet-smart-filters-search' => isset($shortcode_tags['jet-smart-filters-search']) ? 'ZAREJESTROWANY' : 'BRAK',
            'jet-smart-filters-remove-filters' => isset($shortcode_tags['jet-smart-filters-remove-filters']) ? 'ZAREJESTROWANY' : 'BRAK',
            'jet_engine_data' => isset($shortcode_tags['jet_engine_data']) ? 'ZAREJESTROWANY' : 'BRAK',
        ]);

        // Lista WSZYSTKICH zarejestrowanych shortcodów (żeby zobaczyć jak faktycznie nazywają się shortcody)
        $all_shortcodes = array_keys($shortcode_tags);
        $jet_shortcodes = array_filter($all_shortcodes, function($sc) {
            return strpos($sc, 'jet') !== false;
        });
        $this->debug_log('Wszystkie shortcody zaczynające się od "jet"', $jet_shortcodes);

        // Rozpocznij buforowanie outputu
        ob_start();
        ?>
        <div class="letsfight-wyszukiwarka"
             data-listing-id="<?php echo esc_attr($atts['listing_id']); ?>"
             data-miasto-slug="<?php echo esc_attr($miasto_slug); ?>">

            <!-- Panel filtrów -->
            <div class="letsfight-panel">
                <div class="letsfight-panel__filters">

                    <!-- Filtr miasta -->
                    <div class="letsfight-filter letsfight-filter--miasto">
                        <label class="letsfight-filter__label">MIASTO</label>
                        <select class="letsfight-select letsfight-miasto-select" data-current-slug="<?php echo esc_attr($miasto_slug); ?>">
                            <option value="">Wszystkie miasta</option>
                            <?php if (!empty($miasta)): ?>
                                <?php foreach ($miasta as $miasto): ?>
                                    <option value="<?php echo esc_attr($miasto->slug); ?>"
                                            <?php selected($miasto->slug, $miasto_slug); ?>>
                                        <?php echo esc_html($miasto->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Filtr dyscypliny (WŁASNY SELECT - JetSmartFilters nie działa) -->
                    <div class="letsfight-filter letsfight-filter--dyscyplina">
                        <label class="letsfight-filter__label">DYSCYPLINA</label>
                        <select class="letsfight-select letsfight-dyscyplina-select">
                            <option value="">Wszystkie dyscypliny</option>
                            <?php if (!empty($dyscypliny)): ?>
                                <?php foreach ($dyscypliny as $dyscyplina): ?>
                                    <option value="<?php echo esc_attr($dyscyplina->slug); ?>">
                                        <?php echo esc_html($dyscyplina->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Wyszukiwarka (WŁASNE POLE - JetSmartFilters nie działa) -->
                    <div class="letsfight-filter letsfight-filter--search">
                        <label class="letsfight-filter__label">WYSZUKAJ KLUB</label>
                        <input type="text" class="letsfight-input letsfight-search-input" placeholder="Wpisz nazwę klubu...">
                    </div>

                    <!-- Reset filtrów -->
                    <div class="letsfight-filter-reset-wrapper">
                        <button class="letsfight-btn letsfight-btn--reset">
                            <span class="letsfight-btn__icon">🔄</span>
                            <span class="letsfight-btn__text">Wyczyść filtry</span>
                        </button>
                    </div>

                </div>

                <!-- Akcje (toggle + counter) -->
                <div class="letsfight-panel__actions">

                    <!-- Toggle Lista/Mapa -->
                    <div class="letsfight-toggle">
                        <button class="letsfight-toggle__btn letsfight-toggle__btn--active" data-view="lista">
                            <span class="letsfight-toggle__icon">☰</span>
                            <span class="letsfight-toggle__text">Lista</span>
                        </button>
                        <button class="letsfight-toggle__btn" data-view="mapa">
                            <span class="letsfight-toggle__icon">🗺️</span>
                            <span class="letsfight-toggle__text">Mapa</span>
                        </button>
                    </div>

                    <!-- Licznik klubów -->
                    <div class="letsfight-counter">
                        <span class="letsfight-counter__text">Znaleziono:</span>
                        <span class="letsfight-counter__number">0</span>
                        <span class="letsfight-counter__label">klubów</span>
                    </div>

                </div>
            </div>

            <!-- Widok listy (JetEngine Grid) -->
            <div class="letsfight-view letsfight-view--lista letsfight-view--active">
                <?php
                // Dodaj debug przed renderowaniem
                $listing_shortcode = '[jet_engine component="listings_grid" listing_id="' . esc_attr($atts['listing_id']) . '"]';
                $this->debug_log('Renderowanie listingu JetEngine', [
                    'shortcode' => $listing_shortcode,
                    'listing_id' => $atts['listing_id'],
                ]);

                // Renderuj listing
                $listing_output = do_shortcode($listing_shortcode);

                if (empty(trim(strip_tags($listing_output)))) {
                    $this->debug_log('BŁĄD: Listing JetEngine zwrócił pusty output!');
                    echo '<div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 8px; margin: 20px 0;">';
                    echo '<strong>⚠️ Brak klubów do wyświetlenia.</strong><br>';
                    echo 'Shortcode: <code>' . esc_html($listing_shortcode) . '</code>';
                    echo '</div>';
                } else {
                    $this->debug_log('Listing JetEngine renderowany pomyślnie', [
                        'output_length' => strlen($listing_output),
                    ]);
                }

                echo $listing_output;
                ?>
            </div>

            <!-- Widok mapy (Mapbox) -->
            <div class="letsfight-view letsfight-view--mapa">
                <div id="letsfight-map" style="width: 100%; height: 600px;"></div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Wykryj miasto z URL
     */
    private function get_city_from_url() {
        $current_url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
        $url_parts = explode('/', trim($current_url, '/'));

        if (in_array('trenuj', $url_parts)) {
            $key = array_search('trenuj', $url_parts);
            if (isset($url_parts[$key + 1])) {
                return sanitize_title($url_parts[$key + 1]);
            }
        }

        return '';
    }

    /**
     * AJAX Handler - pobierz współrzędne klubów
     */
    public function ajax_get_clubs_coords() {
        // Weryfikacja nonce
        check_ajax_referer('letsfight_map_nonce', 'nonce');

        // Pobierz ID postów
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];

        if (empty($post_ids)) {
            wp_send_json_error(['message' => 'Brak ID postów']);
            return;
        }

        $clubs = [];

        foreach ($post_ids as $post_id) {
            // Sprawdź czy post istnieje
            if (get_post_status($post_id) !== 'publish') {
                continue;
            }

            $lat = get_post_meta($post_id, 'club_latitude', true);
            $lng = get_post_meta($post_id, 'club_longitude', true);

            // Jeśli brak współrzędnych - spróbuj geocodować adres
            if (empty($lat) || empty($lng)) {
                // Spróbuj najpierw 'adres_dla_mapy', potem 'adres' (fallback)
                $address = get_post_meta($post_id, 'adres_dla_mapy', true);
                if (empty($address)) {
                    $address = get_post_meta($post_id, 'adres', true);
                }

                if (!empty($address)) {
                    $coords = $this->geocode_address($address);

                    if ($coords) {
                        $lat = $coords['lat'];
                        $lng = $coords['lng'];

                        // Zapisz współrzędne
                        update_post_meta($post_id, 'club_latitude', $lat);
                        update_post_meta($post_id, 'club_longitude', $lng);
                    }
                }
            }

            // Dodaj klub do listy
            if (!empty($lat) && !empty($lng)) {
                $clubs[] = [
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'lat' => floatval($lat),
                    'lng' => floatval($lng),
                    'url' => get_permalink($post_id),
                    'excerpt' => wp_trim_words(get_the_excerpt($post_id), 15),
                ];
            }
        }

        if (empty($clubs)) {
            wp_send_json_error(['message' => 'Brak klubów z współrzędnymi']);
            return;
        }

        wp_send_json_success($clubs);
    }

    /**
     * Geocodowanie adresu przez Mapbox API
     */
    private function geocode_address($address) {
        if (empty($address)) {
            return null;
        }

        // Sprawdź cache
        $cache_key = 'letsfight_geocode_' . md5($address);
        $cached = get_transient($cache_key);

        if ($cached) {
            return $cached;
        }

        // Wywołaj API Mapbox
        $address_encoded = urlencode($address);
        $url = "https://api.mapbox.com/geocoding/v5/mapbox.places/{$address_encoded}.json?access_token={$this->mapbox_token}&country=pl&limit=1";

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            error_log('LetsFight Mapa - Błąd geocodowania: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['features'][0]['center'])) {
            $coords = [
                'lng' => floatval($body['features'][0]['center'][0]),
                'lat' => floatval($body['features'][0]['center'][1]),
            ];

            // Zapisz w cache na 30 dni
            set_transient($cache_key, $coords, 30 * DAY_IN_SECONDS);

            return $coords;
        }

        return null;
    }

    /**
     * Logowanie debug
     */
    private $debug_messages = [];

    private function debug_log($message, $data = null) {
        if (!$this->debug) {
            return;
        }

        $log_entry = [
            'time' => current_time('H:i:s'),
            'message' => $message,
            'data' => $data,
        ];

        $this->debug_messages[] = $log_entry;

        // Zapisz do error_log
        $log_text = "[LetsFight Mapa {$log_entry['time']}] {$message}";
        if ($data !== null) {
            $log_text .= ': ' . print_r($data, true);
        }
        error_log($log_text);
    }

    /**
     * Wyświetl informacje debug w stopce
     */
    public function output_debug_info() {
        if (empty($this->debug_messages)) {
            return;
        }

        ?>
        <div id="letsfight-debug" style="position: fixed; bottom: 0; left: 0; right: 0; background: #1a1a1a; color: #0f0; padding: 20px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; z-index: 999999; border-top: 3px solid #f79716;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <strong style="color: #f79716;">🐛 LetsFight Mapa - Debug Log</strong>
                <button onclick="this.parentElement.parentElement.style.display='none'" style="background: #f79716; color: #1a1a1a; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;">Zamknij</button>
            </div>
            <div style="background: #000; padding: 10px; border-radius: 5px; overflow-x: auto;">
                <?php foreach ($this->debug_messages as $entry): ?>
                    <div style="margin-bottom: 10px; border-left: 3px solid #f79716; padding-left: 10px;">
                        <div style="color: #999;">[<?php echo esc_html($entry['time']); ?>]</div>
                        <div style="color: #0f0; font-weight: bold;"><?php echo esc_html($entry['message']); ?></div>
                        <?php if ($entry['data'] !== null): ?>
                            <pre style="color: #ff0; margin: 5px 0 0 0; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html(print_r($entry['data'], true)); ?></pre>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 10px; padding: 10px; background: #333; border-radius: 5px;">
                <strong style="color: #f79716;">📋 Kopiuj poniższy tekst do zgłoszenia błędu:</strong>
                <textarea readonly style="width: 100%; height: 100px; margin-top: 5px; background: #000; color: #0f0; border: 1px solid #f79716; padding: 10px; font-family: monospace; font-size: 11px;"><?php
                    foreach ($this->debug_messages as $entry) {
                        echo "[{$entry['time']}] {$entry['message']}";
                        if ($entry['data'] !== null) {
                            echo "\n" . print_r($entry['data'], true);
                        }
                        echo "\n---\n";
                    }
                ?></textarea>
            </div>
        </div>
        <?php
    }
}

// Inicjalizacja wtyczki
function letsfight_mapa_init() {
    return LetsFight_Mapa::get_instance();
}

add_action('plugins_loaded', 'letsfight_mapa_init');
